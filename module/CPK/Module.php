<?php
namespace CPK;
use Zend\ModuleManager\ModuleManager,
    Zend\Mvc\MvcEvent,
    Zend\ModuleManager\ModuleEvent;

class Module
{
	public function __construct()
	{}

    /**
     * Get module configuration
     *
     * @return array
     */
    public function getConfig()
    {
        return include __DIR__ . '/config/module.config.php';
    }

    /**
     * Get autoloader configuration
     *
     * @return array
     */
    public function getAutoloaderConfig()
    {
        return array(
            'Zend\Loader\StandardAutoloader' => array(
                'namespaces' => array(
                    __NAMESPACE__ => __DIR__ . '/src/' . __NAMESPACE__,
                ),
            ),
        );
    }

    /**
     * Initialize the module
     *
     * @param ModuleManager $m Module manager
     *
     * @return void
     */
    public function init(ModuleManager $moduleManager)
    {
        $events = $moduleManager->getEventManager();
        $events->attach(ModuleEvent::EVENT_MERGE_CONFIG, array($this, 'onMergeConfig'));
    }

    /**
     * Bootstrap the module
     *
     * @param MvcEvent $e Event
     *
     * @return void
     */
    public function onBootstrap(MvcEvent $e)
    {
    }

    public function onMergeConfig(ModuleEvent $e)
    {
        $configListener = $e->getConfigListener();
        $config         = $configListener->getMergedConfig(false);

        // Modify the configuration; here, we'll remove a specific key:
        if (isset($config['service_manager']['invokables']['VuFind\Search'])) {
            unset($config['service_manager']['invokables']['VuFind\Search']);
        }

        if (isset($config['controllers']['invokables']['myresearch'])) {
            unset($config['controllers']['invokables']['myresearch']);
        }

        // Pass the changed configuration back to the listener:
        $configListener->setMergedConfig($config);
    }
}
