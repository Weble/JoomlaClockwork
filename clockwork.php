<?php
defined('_JEXEC') or die;

/**
 * Joomla! Debug plugin.
 *
 * @since  1.5
 */
class PlgSystemClockwork extends JPlugin
{
    /**
     * @var Clockwork\Clockwork
     */
    protected $clockwork;

    /**
     * Constructor.
     *
     * @param   object &$subject The object to observe.
     * @param   array $config An optional associative array of configuration settings.
     *
     * @since   1.5
     */
    public function __construct (&$subject, $config)
    {
        parent::__construct($subject, $config);

        // JDebug needs to be enabled to log queries
        if (!JDEBUG) {
            return;
        }

        // User has to be authorised to see the debug information.
        if (!$this->isAuthorisedDisplayDebug()) {
            return;
        }

        $this->setupClockWork();
    }

    /**
     *
     */
    public function onAfterInitialise ()
    {
        if (\JRequest::getVar('clockwork', false) == 'getClockworkDetails') {
            require_once __DIR__ . '/vendor/autoload.php';

            $clockwork = new Clockwork\Clockwork;
            $clockwork->setStorage(new Clockwork\Storage\SqlStorage('sqlite:' . JPATH_SITE . '/cache/clockwork.sqlite'));
            header('Content-Type', 'application/json');

            echo $clockwork->getStorage()->find($_GET['id'])->toJson();
            exit();
        }
    }

    /**
     * Show the debug info.
     *
     * @return  void
     *
     * @since   1.6
     */
    public function onAfterRespond ()
    {
        // JDebug needs to be enabled to log queries
        if (!JDEBUG) {
            return;
        }

        // User has to be authorised to see the debug information.
        if (!$this->isAuthorisedDisplayDebug()) {
            return;
        }

        $this->resolveClockworkRequest();
    }

    /**
     *
     */
    protected function setupClockWork ()
    {
        require_once __DIR__ . '/vendor/autoload.php';

        $clockwork = new Clockwork\Clockwork;

        $clockwork->addDataSource(new Clockwork\DataSource\PhpDataSource);
        $clockwork->addDataSource(new \Weble\JoomlaClockwork\JoomlaDbDataSource());

        $this->clockwork = $clockwork;
    }

    /**
     *
     */
    protected function resolveClockworkRequest()
    {
        $storage = new Clockwork\Storage\SqlStorage('sqlite:' . JPATH_SITE . '/cache/clockwork.sqlite');

        $this->clockwork->setStorage($storage);
        $this->clockwork->resolveRequest()->storeRequest();

        header('X-Clockwork-Id: ' . $this->clockwork->getRequest()->id);
        header('X-Clockwork-Version: ' . Clockwork\Clockwork::VERSION);

        header('X-Clockwork-Path:' . '/index.php?clockwork=getClockworkDetails&id=');
    }

    /**
     * Method to check if the current user is allowed to see the debug information or not.
     *
     * @return  boolean  True if access is allowed.
     *
     * @since   3.0
     */
    private function isAuthorisedDisplayDebug ()
    {
        static $result = null;

        if ($result !== null) {
            return $result;
        }

        // If the user is not allowed to view the output then end here.
        $filterGroups = (array)$this->params->get('filter_groups', null);

        if (!empty($filterGroups)) {
            $userGroups = \JFactory::getUser()->get('groups');

            if (!array_intersect($filterGroups, $userGroups)) {
                $result = false;
                return false;
            }
        }

        $result = true;

        return true;
    }
}
