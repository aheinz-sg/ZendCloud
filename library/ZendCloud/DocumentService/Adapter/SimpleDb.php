<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/zf2 for the canonical source repository
 * @copyright Copyright (c) 2005-2012 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 * @package   Zend_Cloud
 */

namespace ZendCloud\DocumentService\Adapter;

use Traversable;
use ZendCloud\DocumentService\Adapter\SimpleDb\Query;
use ZendCloud\DocumentService\Document;
use ZendService\Amazon\Exception as AmazonException;
use ZendService\Amazon\SimpleDb\Attribute;
use ZendService\Amazon\SimpleDb\SimpleDb as SimpleDbService;
use Zend\Stdlib\ArrayUtils;

/**
 * SimpleDB adapter for document service.
 *
 * @category   Zend
 * @package    Zend_Cloud_DocumentService
 * @subpackage Adapter
 */
class SimpleDb extends AbstractAdapter
{
    /*
     * Options array keys for the SimpleDB adapter.
     */
    const AWS_ACCESS_KEY   = 'aws_accesskey';
    const AWS_SECRET_KEY   = 'aws_secretkey';

    const ITEM_NAME        = 'ItemName';

    const MERGE_OPTION     = "merge";
    const RETURN_DOCUMENTS = "return_documents";

    const DEFAULT_QUERY_CLASS = 'ZendCloud\DocumentService\Adapter\SimpleDb\Query';


    /**
     * SQS service instance.
     * @var \ZendService\Amazon\SimpleDb\SimpleDb
     */
    protected $_simpleDb;

    /**
     * Class to utilize for new query objects
     * @var string
     */
    protected $_queryClass = 'ZendCloud\DocumentService\Adapter\SimpleDb\Query';

    /**
     * Constructor
     *
     * @param array|Traversable $options
     */
    public function __construct($options = array())
    {
        if ($options instanceof Traversable) {
            $options = ArrayUtils::iteratorToArray($options);
        }

        if (!is_array($options)) {
            throw new Exception\InvalidArgumentException('Invalid options provided to constructor');
        }

        $this->_simpleDb = new SimpleDbService(
            $options[self::AWS_ACCESS_KEY], $options[self::AWS_SECRET_KEY]
        );

        if (isset($options[self::HTTP_ADAPTER])) {
            $this->_simpleDb->getHttpClient()->setAdapter($options[self::HTTP_ADAPTER]);
        }

        if (isset($options[self::DOCUMENT_CLASS])) {
            $this->setDocumentClass($options[self::DOCUMENT_CLASS]);
        }

        if (isset($options[self::DOCUMENTSET_CLASS])) {
            $this->setDocumentSetClass($options[self::DOCUMENTSET_CLASS]);
        }

        if (isset($options[self::QUERY_CLASS])) {
            $this->setQueryClass($options[self::QUERY_CLASS]);
        }
    }

    /**
     * Create collection.
     *
     * @param  string $name
     * @param  array  $options
     * @return void
     */
    public function createCollection($name, $options = null)
    {
        try {
            $this->_simpleDb->createDomain($name);
        } catch(\ZendService\Amazon\Exception $e) {
            throw new Exception\RunTimeException('Error on domain creation: '.$e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Delete collection.
     *
     * @param  string $name
     * @param  array  $options
     * @return void
     */
    public function deleteCollection($name, $options = null)
    {
        try {
            $this->_simpleDb->deleteDomain($name);
        } catch(\ZendService\Amazon\Exception $e) {
            throw new Exception\RunTimeException('Error on collection deletion: '.$e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * List collections.
     *
     * @param  array  $options
     * @return array
     */
    public function listCollections($options = null)
    {
        try {
            // TODO package this in Pages
            $domains = $this->_simpleDb->listDomains()->getData();
        } catch(\ZendService\Amazon\Exception $e) {
            throw new Exception\RuntimeException('Error on collection deletion: '.$e->getMessage(), $e->getCode(), $e);
        }

        return $domains;
    }

    /**
     * List documents
     *
     * Returns a key/value array of document names to document objects.
     *
     * @param  string $collectionName Name of collection for which to list documents
     * @param  array|null $options
     * @return \ZendCloud\DocumentService\DocumentSet
     */
    public function listDocuments($collectionName, array $options = null)
    {
        $query = $this->select('*')->from($collectionName);
        $items = $this->query($collectionName, $query, $options);
        return $items;
    }

    /**
     * Insert document
     *
     * @param  string $collectionName Collection into which to insert document
     * @param  array|\ZendCloud\DocumentService\Document $document
     * @param  array $options
     * @return void
     */
    public function insertDocument($collectionName, $document, $options = null)
    {
        if (is_array($document)) {
            $document =  $this->_getDocumentFromArray($document);
        }

        if (!$document instanceof Document) {
            throw new Exception\InvalidArgumentException('Invalid document supplied');
        }

        try {
            $this->_simpleDb->putAttributes(
                $collectionName,
                $document->getID(),
                $this->_makeAttributes($document->getID(), $document->getFields())
            );
        } catch(AmazonException $e) {
            throw new Exception\RunTimeException('Error on document insertion: '.$e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Replace an existing document with a new version
     *
     * @param  string $collectionName
     * @param  array|\ZendCloud\DocumentService\Document $document
     * @param  array $options
     * @return void
     */
    public function replaceDocument($collectionName, $document, $options = null)
    {
        if (is_array($document)) {
            $document =  $this->_getDocumentFromArray($document);
        }

        if (!$document instanceof \ZendCloud\DocumentService\Document) {
            throw new Exception\InvalidArgumentException('Invalid document supplied');
        }

        // Delete document first, then insert. PutAttributes always keeps any
        // fields not referenced in the payload, but present in the document
        $documentId = $document->getId();
        $fields     = $document->getFields();
        $docClass   = get_class($document);
        $this->deleteDocument($collectionName, $document, $options);

        $document   = new $docClass($fields, $documentId);
        $this->insertDocument($collectionName, $document);
    }

    /**
     * Update document. The new document replaces the existing document.
     *
     * Option 'merge' specifies to add all attributes (if true) or
     * specific attributes ("attr" => true) instead of replacing them.
     * By default, attributes are replaced.
     *
     * @param  string $collectionName
     * @param  mixed|\ZendCloud\DocumentService\Document $documentId Document ID, adapter-dependent
     * @param  array|\ZendCloud\DocumentService\Document $fieldset Set of fields to update
     * @param  array $options
     * @return boolean
     */
    public function updateDocument($collectionName, $documentId, $fieldset = null, $options = null)
    {
        if (null === $fieldset && $documentId instanceof \ZendCloud\DocumentService\Document) {
            $fieldset   = $documentId->getFields();
            if (empty($documentId)) {
                $documentId = $documentId->getId();
            }
        } elseif ($fieldset instanceof \ZendCloud\DocumentService\Document) {
            if (empty($documentId)) {
                $documentId = $fieldset->getId();
            }
            $fieldset = $fieldset->getFields();
        }

        $replace = array();
        if (empty($options[self::MERGE_OPTION])) {
            // no merge option - we replace all
            foreach ($fieldset as $key => $value) {
                $replace[$key] = true;
            }
        } elseif (is_array($options[self::MERGE_OPTION])) {
            foreach ($fieldset as $key => $value) {
                if (empty($options[self::MERGE_OPTION][$key])) {
                    // if there's merge key, we add it, otherwise we replace it
                    $replace[$key] = true;
                }
            }
        } // otherwise $replace is empty - all is merged

        try {
            $this->_simpleDb->putAttributes(
                $collectionName,
                $documentId,
                $this->_makeAttributes($documentId, $fieldset),
                $replace
            );
        } catch(\ZendService\Amazon\Exception $e) {
            throw new Exception\RunTimeException('Error on document update: '.$e->getMessage(), $e->getCode(), $e);
        }
        return true;
    }

    /**
     * Delete document.
     *
     * @param  string $collectionName Collection from which to delete document
     * @param  mixed  $document Document ID or Document object.
     * @param  array  $options
     * @return boolean
     */
    public function deleteDocument($collectionName, $document, $options = null)
    {
        if ($document instanceof \ZendCloud\DocumentService\Document) {
            $document = $document->getId();
        }
        try {
            $this->_simpleDb->deleteAttributes($collectionName, $document);
        } catch(\ZendService\Amazon\Exception $e) {
            throw new Exception\RuntimeException('Error on document deletion: '.$e->getMessage(), $e->getCode(), $e);
        }
        return true;
    }

    /**
     * Fetch single document by ID
     *
     * @param  string $collectionName Collection name
     * @param  mixed $documentId Document ID, adapter-dependent
     * @param  array $options
     * @return \ZendCloud\DocumentService\Document
     */
    public function fetchDocument($collectionName, $documentId, $options = null)
    {
        try {
            $attributes = $this->_simpleDb->getAttributes($collectionName, $documentId);
            if ($attributes == false || count($attributes) == 0) {
                return false;
            }
            return $this->_resolveAttributes($attributes, true);
        } catch(\ZendService\Amazon\Exception $e) {
            throw new Exception\RunTimeException('Error on fetching document: '.$e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Query for documents stored in the document service. If a string is passed in
     * $query, the query string will be passed directly to the service.
     *
     * @param  string $collectionName Collection name
     * @param  string $query
     * @param  array $options
     * @return array|\ZendCloud\DocumentService\DocumentSet
     */
    public function query($collectionName, $query, $options = null)
    {
        $returnDocs = isset($options[self::RETURN_DOCUMENTS])
                    ? (bool) $options[self::RETURN_DOCUMENTS]
                    : true;

        try {
            if ($query instanceof \ZendCloud\DocumentService\Adapter\SimpleDb\Query) {
                $query = $query->assemble($collectionName);
            }
            $result = $this->_simpleDb->select($query);
        } catch(\ZendService\Amazon\Exception $e) {
            throw new Exception\RuntimeException('Error on document query: '.$e->getMessage(), $e->getCode(), $e);
        }

        return $this->_getDocumentSetFromResultSet($result, $returnDocs);
    }

    /**
     * Create query statement
     *
     * @param  string $fields
     * @return \ZendCloud\DocumentService\Adapter\SimpleDb\Query
     */
    public function select($fields = null)
    {
        $queryClass = $this->getQueryClass();

        $query = new $queryClass($this);
        $defaultClass = self::DEFAULT_QUERY_CLASS;
        if (!$query instanceof $defaultClass) {
            throw new Exception\RunTimeException('Query class must extend ' . self::DEFAULT_QUERY_CLASS);
        }

        $query->select($fields);
        return $query;
    }

    /**
     * Get the concrete service client
     *
     * @return \ZendService\Amazon\SimpleDb\SimpleDb
     */
    public function getClient()
    {
        return $this->_simpleDb;
    }

    /**
     * Convert array of key-value pairs to array of Amazon attributes
     *
     * @param string $name
     * @param array $attributes
     * @return array
     */
    protected function _makeAttributes($name, $attributes)
    {
        $result = array();
        foreach ($attributes as $key => $attr) {
            $result[] = new Attribute($name, $key, $attr);
        }
        return $result;
    }

    /**
     * Convert array of Amazon attributes to array of key-value pairs
     *
     * @param array $attributes
     * @return array
     */
    protected function _resolveAttributes($attributes, $returnDocument = false)
    {
        $result = array();
        foreach ($attributes as $attr) {
            $value = $attr->getValues();
            if (count($value) == 0) {
                $value = null;
            } elseif (count($value) == 1) {
                $value = $value[0];
            }
            $result[$attr->getName()] = $value;
        }

        // Return as document object?
        if ($returnDocument) {
            $documentClass = $this->getDocumentClass();
            return new $documentClass($result, $attr->getItemName());
        }

        return $result;
    }

    /**
     * Create suitable document from array of fields
     *
     * @param array $document
     * @return \ZendCloud\DocumentService\Document
     */
    protected function _getDocumentFromArray($document)
    {
        if (!isset($document[Document::KEY_FIELD])) {
            if (isset($document[self::ITEM_NAME])) {
                $key = $document[self::ITEM_NAME];
                unset($document[self::ITEM_NAME]);
            } else {
                throw new Exception\InvalidArgumentException('Fields array should contain the key field '.Document::KEY_FIELD);
            }
        } else {
            $key = $document[Document::KEY_FIELD];
            unset($document[Document::KEY_FIELD]);
        }

        $documentClass = $this->getDocumentClass();
        return new $documentClass($document, $key);
    }

    /**
     * Create a DocumentSet from a SimpleDb resultSet
     *
     * @param  \ZendService\Amazon\SimpleDb\Page $resultSet
     * @param  bool $returnDocs
     * @return \ZendCloud\DocumentService\DocumentSet
     */
    protected function _getDocumentSetFromResultSet(\ZendService\Amazon\SimpleDb\Page $resultSet, $returnDocs = true)
    {
        $docs = array();
        foreach ($resultSet->getData() as $item) {
            $docs[] = $this->_resolveAttributes($item, $returnDocs);
        }

        $setClass = $this->getDocumentSetClass();
        return new $setClass($docs);
    }
}
