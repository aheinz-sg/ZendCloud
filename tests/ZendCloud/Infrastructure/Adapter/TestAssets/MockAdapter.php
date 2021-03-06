<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/zf2 for the canonical source repository
 * @copyright Copyright (c) 2005-2012 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 * @package   Zend_Cloud
 */

namespace ZendCloudTest\Infrastructure\Adapter\TestAssets;

use ZendCloud\Infrastructure\Adapter\AbstractAdapter;

class MockAdapter extends AbstractAdapter
{
    /**
     * Simulate waiting for status $status.
     *
     * @param  string $id
     * @param  string $status
     * @param  integer $timeout
     * @return boolean
     */
    public function waitStatusInstance($id, $status, $timeout = self::TIMEOUT_STATUS_CHANGE)
    {
        if (empty($id) || empty($status)) {
            return false;
        }

        return true;
    }

    /**
     * Return the status of an instance
     *
     * @param  string $id
     * @return string
     */
    public function statusInstance($id)
    {
        return 'status';
    }

    /**
     * Return the public DNS name of the instance
     *
     * @param  string $id
     * @return string|boolean
     */
    public function publicDnsInstance($id)
    {
        return 'public-dns';
    }

    /**
     * Reboot an instance
     *
     * @param string $id
     * @return boolean
     */
    public function rebootInstance($id)
    {
        return true;
    }

    /**
     * Create a new instance
     *
     * @param  string $name
     * @param  array $options
     * @return boolean
     */
    public function createInstance($name, $options)
    {
        return true;
    }


    /**
     * Stop the execution of an instance
     *
     * @param  string $id
     * @return boolean
     */
    public function stopInstance($id)
    {
        return true;
    }

    /**
     * Start the execution of an instance
     *
     * @param  string $id
     * @return boolean
     */
    public function startInstance($id)
    {
        return true;
    }

    /**
     * Destroy an instance
     *
     * @param  string $id
     * @return boolean
     */
    public function destroyInstance($id)
    {
        return true;
    }

    /**
     * Return all the available instances images
     *
     * @return ImageList
     */
    public function imagesInstance()
    {
        return array();
    }

    /**
     * Return all the available zones
     *
     * @return array
     */
    public function zonesInstance()
    {
        return array();
    }

    /**
     * Return the system informations about the $metric of an instance
     *
     * @param  string $id
     * @param  string $metric
     * @param  array $options
     * @return array
     */
    public function monitorInstance($id, $metric, $options = null)
    {
        return array();
    }

    /**
     * Run arbitrary shell script on an instance
     *
     * @param  string $id
     * @param  array $param
     * @param  string|array $cmd
     * @return string|array
     */
    public function deployInstance($id, $params, $cmd)
    {
        return 'result';
    }

    /**
     * Return a list of the available instances
     *
     * @return InstanceList
     */
    public function listInstances()
    {
        return array();
    }

    /**
     * Get the adapter instance
     *
     * @return object
     */
    public function getAdapter()
    {
        return $this;
    }

    /**
     * Get the adapter result
     *
     * @return array
     */
    public function getAdapterResult()
    {
        return array();
    }

    /**
     * Get the last HTTP response
     *
     * @return \Zend\Http\Response
     */
    public function getLastHttpResponse()
    {
        return new \Zend\Http\Response();
    }

    /**
     * Get the last HTTP request
     *
     * @return string
     */
    public function getLastHttpRequest()
    {
        return new \Zend\Http\Request();
    }
}
