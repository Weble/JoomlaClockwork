<?php

namespace Weble\JoomlaClockwork;

use Clockwork\DataSource\DataSource;
use Clockwork\Request\Log;
use Clockwork\Request\Request;
use Clockwork\Request\Timeline;

class JoomlaDbDataSource extends DataSource
{
    /**
     * @var array
     */
    protected $queries = [];

    /**
     * Log data structure
     */
    protected $log;

    /**
     * Timeline data structure
     */
    protected $timeline;

    /**
     * Create a new data source, takes Laravel application instance as an argument
     */
    public function __construct ()
    {
        $this->setupJoomlaLogger();

        $this->log = new Log();
        $this->timeline = new Timeline();
    }

    /**
     * Store log messages so they can be displayed later.
     * This function is passed log entries by JLogLoggerCallback.
     *
     * @param   JLogEntry $entry A log entry.
     *
     * @return  void
     *
     * @since   3.1
     */
    public function logger (\JLogEntry $entry)
    {
        $this->log->log($entry->category, $entry->message, $entry->context);
    }

    /**
     *
     */
    protected function setupJoomlaLogger ()
    {
        $priority = 0;

        $logPriorities = [
            "all",
            "emergency",
            "alert",
            "critical",
            "error",
            "warning",
            "notice",
            "info",
            "debug",
        ];

        foreach ($logPriorities as $p) {
            $const = '\JLog::' . strtoupper($p);

            if (!defined($const)) {
                continue;
            }

            $priority |= constant($const);
        }

        \JLog::addLogger(['logger' => 'callback', 'callback' => [$this, 'logger']], $priority, [], 0);
    }

    /**
     * Adds ran database queries to the request
     */
    public function resolve (Request $request)
    {

        // $request->controller = $this->getController();
        $request->sessionData = $this->getSessionData();
        $request->log = array_merge($request->log, $this->getLogData());
        $request->timelineData = $this->getTimelineData($request);
        $request->databaseQueries = array_merge($request->databaseQueries, $this->getDatabaseQueries());

        return $request;
    }

    /**
     * @return array
     */
    protected function getLogData ()
    {
        return $this->log->toArray();
    }

    /**
     *
     */
    protected function getTimelineData (Request $request)
    {
        $profiler = \JProfiler::getInstance('Application');

        $reflection = new \ReflectionClass($profiler);
        $property = $reflection->getProperty('start');
        $property->setAccessible(true);

        $startTime =  $property->getValue($profiler);

        foreach ($profiler->getMarks() as $mark) {
            $this->timeline->addEvent($mark->label, $mark->label, $startTime / 1000, ($startTime + $mark->time) / 1000, [
                'memory' => $mark->memory
            ]);
        }

        return $this->timeline->finalize($request->time);
    }

    /**
     * Log the query into the internal store
     */
    public function registerQuery ($query, $time, $callStack = [])
    {
        $databaseExecuteIndex = false;
        foreach ($callStack as $index => $call) {
            if (isset($call['file']) && stripos($call['file'], 'libraries/joomla/database/driver.php') !== false) {
                $databaseExecuteIndex = $index + 1;
            }
        }

        $this->queries[] = [
            'query' => (string)$query,
            'time' => $time,
            'trace' => $callStack,
            'file' => $databaseExecuteIndex ? $callStack[$databaseExecuteIndex]['file'] : '',
            'line' => $databaseExecuteIndex ? $callStack[$databaseExecuteIndex]['line'] : '',
            'model' => $databaseExecuteIndex ? $callStack[$databaseExecuteIndex]['class'] : ''
        ];
    }

    /**
     * Returns an array of runnable queries and their durations from the internal array
     */
    protected function getDatabaseQueries ()
    {
        /** @var \JDatabaseDriver $db */
        $db = \JFactory::getDbo();
        $log = $db->getLog();

        if ($log) {
            $timings = $db->getTimings();
            $callStacks = $db->getCallStacks();

            foreach ($log as $id => $query) {
                $queryTime = 0;
                $callStack = [];

                if ($timings && isset($timings[$id * 2 + 1])) {
                    // Compute the query time.
                    $queryTime = ($timings[$id * 2 + 1] - $timings[$id * 2]) * 1000;
                }

                if ($callStacks[$id]) {
                    $callStack = $callStacks[$id];
                }

                $this->registerQuery($query, $queryTime, $callStack);
            }
        }

        return array_map(function ($query) {
            return [
                'query' => $query['query'],
                'duration' => $query['time'],
                'connection' => 'joomla',
                'trace' => $query['trace'],
                'file' => $query['file'],
                'line' => $query['line'],
                'model' => $query['model']
            ];
        }, $this->queries);
    }

    /**
     * @return mixed
     */
    protected function getSessionData ()
    {
        return \JFactory::getSession()->getData();
    }

}