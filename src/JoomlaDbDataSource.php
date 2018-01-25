<?php

namespace Weble\JoomlaClockwork;

use Clockwork\DataSource\DataSource;
use Clockwork\Request\Request;

/**
 * Data source for Eloquent (Laravel ORM), provides database queries
 */
class JoomlaDbDataSource extends DataSource
{
    protected $queries = [];

    /**
     * Log the query into the internal store
     */
    public function registerQuery($query, $time, $callStack = [])
    {
        $databaseExecuteIndex = false;
        foreach ($callStack as $index => $call) {
            if (stripos($call['file'], 'libraries/joomla/database/driver.php') !== false) {
                $databaseExecuteIndex = $index + 1;
            }
        }

        $this->queries[] = [
            'query'      => (string) $query,
            'time'       => $time,
            'file'      =>  $databaseExecuteIndex ? $callStack[$databaseExecuteIndex]['file'] : '',
            'line'      =>  $databaseExecuteIndex ? $callStack[$databaseExecuteIndex]['line'] : '',
            'model'     =>  $databaseExecuteIndex ? $callStack[$databaseExecuteIndex]['class'] : ''
        ];
    }

    /**
     * Returns an array of runnable queries and their durations from the internal array
     */
    protected function getDatabaseQueries()
    {
        return array_map(function ($query) {
            return [
                'query'      => $query['query'],
                'duration'   => $query['time'],
                'connection' => 'joomla',
                'file'       => $query['file'],
                'line'       => $query['line'],
                'model'      => $query['model']
            ];
        }, $this->queries);
    }

    /**
     * Adds ran database queries to the request
     */
    public function resolve(Request $request)
    {
        $request->databaseQueries = array_merge($request->databaseQueries, $this->getDatabaseQueries());
        return $request;
    }

}