<?php
namespace philelson\Twilight;

use Phue\Client;

/**
 * Class Twilight
 * @package philelson\Twilight
 * @author Phil Elson <phil@pegasus-commerce.com>
 */
abstract class AbstractWatcher
{
    /** Default delay between sunset checks */
    const DEFAULT_CHECK_DELAY_SECONDS   = 60;

    /** config node name for the hub ip address */
    const CONFIG_ELEMENT_HUB_IP         = 'hub_ip';

    /** config node name for the hub username */
    const CONFIG_ELEMENT_USERNAME       = 'username';

    /** config node name for the group of lights to be controlled */
    const CONFIG_ELEMENT_GROUP          = 'group';

    /** config node name for the verbosity 1 = file and log */
    const CONFIG_ELEMENT_VERBOSE        = 'verbose';

    /** config node name for the config file name */
    const CONFIG_ELEMENT_CONFIG_FILE    = 'config_file';

    /** config node name for the offset from the subset in minutes */
    const CONFIG_ELEMENT_OFFSET_MINS    = 'offset_minutes';

    /** config node name for the delay in seconds between the subset check */
    const CONFIG_ELEMENT_CHECK_DELAY    = 'check_delay_seconds';

    /**
     * Client object
     *
     * @var null|\Phue\Client
     */
    protected $_client                  = null;

    /**
     * @var null|\Phue\Group
     */
    protected $_group                   = null;

    /**
     * @var bool
     */
    protected $_verbose                 = false;

    /**
     * @var int
     */
    protected $_offsetInMinutes         = 0;

    /**
     * @var int
     */
    protected $_loopDelay               = self::DEFAULT_CHECK_DELAY_SECONDS;

    /**
     * @var array
     */
    protected $_configIndexes           = [self::CONFIG_ELEMENT_HUB_IP, self::CONFIG_ELEMENT_USERNAME, self::CONFIG_ELEMENT_GROUP];

    /**
     * Method blocks until the sunset has passed.
     * sleep is fine here as there's nothing else for the system to do and it will free
     * resources for other processes.
     *
     * @throws \Exception
     */
    public function run()
    {
        try {
            $this->_init();
            $this->_initLights();
            $this->_beforeLoop();

            do {
                $this->_loop();
            } while (true === $this->_while());

            $this->_afterLoop();
            $this->_trigger();
        } catch (\Exception $exception) {
            $this->_exception($exception);
        }
    }

    /**
     * @return string
     */
    public function getThresholdName()
    {
        static $thresholdName = null;

        if (null === $thresholdName) {
            $classNameParts = explode('\\', get_class($this));
            $thresholdName  = end($classNameParts);
        }

        return $thresholdName;
    }

    /**
     * What do do once the time has lapsed
     *
     * @return mixed
     */
    protected abstract function _trigger();

    /**
     * Returns unix timestamp
     *
     * @param bool $offset
     * @return int
     */
    protected abstract function _getThreshold($offset=true);

    /**
     * Return true to continue in loop
     *
     * @return bool
     */
    protected function _while()
    {
        return (time() < $this->_getThreshold());
    }

    /**
     * Turn lights off
     *
     * @return void
     */
    protected function _initLights()
    {
        $this->_group->setOn(false);
    }

    /**
     * Runs before the main loop
     */
    protected function _beforeLoop()
    {
        $this->_log($this->_getTimeMessage());
    }

    /**
     * Loop body
     */
    protected function _loop()
    {
        sleep($this->_loopDelay);
    }

    /**
     * Runs after the main loop, before the trigger
     */
    protected function _afterLoop()
    {
        $this->_log($this->_getTimeMessage());
    }

    /**
     * Default handling of an exception
     *
     * @param \Exception $exception
     */
    protected function _exception(\Exception $exception)
    {
        $this->_log("Error: ".$exception->getMessage(), FILE_APPEND, true);
    }

    /**
     * @return string
     * @throws \Exception
     */
    protected function _getTimeMessage()
    {
        static $baseMessage = null;

        if (null === $baseMessage) {
            $thresholdName  = $this->getThresholdName();
            $baseMessage    = sprintf("Real %s is at '%s' ", $thresholdName, $this->_getDateString($this->_getThreshold(false)));
            $baseMessage    .= sprintf("Offset %s is +%smins at '%s' ", $thresholdName, $this->_offsetInMinutes, $this->_getDateString($this->_getThreshold()));
        }

        $looping = (true === $this->_while()) ? 'starting loop' : '';

        return $baseMessage.sprintf("time now is '%s' - %s", $this->_getDateString(), $looping);
    }

    /**
     * @throws \Exception
     */
    protected function _init()
    {
        $config = $this->_getConfig();

        foreach ($this->_configIndexes as $requiredIndex) {
            if (false === isset($config[$requiredIndex])) {
                throw new \Exception(sprintf("Config node %s is required", $requiredIndex));
            }
        }

        $this->_client = new Client($config[self::CONFIG_ELEMENT_HUB_IP], $config[self::CONFIG_ELEMENT_USERNAME]);

        $this->_initFromConfig($config);
        $this->_logInitMessage(true);
    }

    /**
     * Initialise from config
     *
     * @param array $config
     */
    protected function _initFromConfig(array $config)
    {
        $hubIp                  = $config[self::CONFIG_ELEMENT_HUB_IP];
        $this->_client          = new Client($hubIp, $config[self::CONFIG_ELEMENT_USERNAME]);
        $this->_group           = $this->_getGroup($config[self::CONFIG_ELEMENT_GROUP]);
        $this->_verbose         = (bool)$this->_getFromConfig($config, self::CONFIG_ELEMENT_VERBOSE, false);
        $this->_offsetInMinutes = $this->_getFromConfig($config, self::CONFIG_ELEMENT_OFFSET_MINS, 0);
        $this->_loopDelay       = $this->_getFromConfig($config, self::CONFIG_ELEMENT_CHECK_DELAY, self::DEFAULT_CHECK_DELAY_SECONDS);

        $this->_log(sprintf("%s watcher started at  '%s'", $this->getThresholdName(), $this->_getDateString()));
        $this->_logInitMessage('Config', $this->_getConfigFileName());
        $this->_logInitMessage('Hub', $hubIp);
        if (0 != $this->_offsetInMinutes) {
            $this->_logInitMessage(sprintf("%s Offset", $this->getThresholdName()), $this->_offsetInMinutes);
        }
        $this->_logInitMessage('Verbosity', ($this->_verbose) ? 'high' : 'low');
        $this->_logInitMessage('Loop Delay', $this->_loopDelay);
    }

    /**
     * @param $label
     * @param $message
     */
    protected function _logInitMessage($label, $message=null)
    {
        static $messages            = [];
        static $longestLabelLength  = 0;

        if (true !== $label) {
            $messages[]      = ['message' => $message, 'label' => $label];
            if (strlen($label) >= $longestLabelLength) {
                $longestLabelLength = strlen($label);
            }
            return;
        }

        $padding = function($label, $max) {
            return (str_repeat(' ', $max - strlen($label) + 5));
        };

        foreach($messages as $message) {
            $label = $message['label'];
            $this->_log(sprintf('%s: %s %s', $label, $padding($label, $longestLabelLength), $message['message']));
        }
    }

    /**
     * @return array
     * @throws \Exception
     */
    protected function _getConfig()
    {
        $pathToConfigFile = $this->_getPath('/../'.$this->_getConfigFileName());

        if (false === file_exists($pathToConfigFile)) {
            throw new \Exception(sprintf("Config file '%s' not found", self::DEFAULT_CONFIG_FILE));
        }

        return json_decode(file_get_contents($pathToConfigFile), true);
    }
    /**
     * @param $config
     * @param $node
     * @param $default
     * @return mixed
     */
    protected function _getFromConfig($config, $node, $default)
    {
        if (false === isset($config[$node])) {
            return $default;
        }

        return $config[$node];
    }

    /**
     * @param $groupId
     * @return mixed
     */
    protected function _getGroup($groupId)
    {
        $groups = $this->_client->getGroups();

        return $groups[$groupId];
    }

    /**
     * @param $message
     * @param int $flag
     * @param bool $verbose
     */
    protected function _log($message, $flag=FILE_APPEND, $verbose=false)
    {
        static $pathToLog = null;

        if (null === $pathToLog) {
            $pathToLog = $this->_getPath('../twilight.log');
        }

        $message = (true === is_array($message)) ? print_r($message, true) : $message;
        $message = $this->getThresholdName().': '.$message;

        file_put_contents($pathToLog, $message."\n", $flag);

        if (true === $this->_verbose || true === $verbose) {
            echo $message."\n";
        }
    }

    /**
     * @param null $timestamp
     * @return bool|string
     */
    protected function _getDateString($timestamp=null)
    {
        $timestamp = (null === $timestamp) ? time() : $timestamp;

        return date("D M j G:i:s T Y", $timestamp);
    }

    /**
     * @param $fileName
     * @return string
     */
    protected function _getPath($fileName)
    {
        return (__DIR__.'/'.$fileName);
    }

    /**
     * Relative to root of project
     *
     * @return string
     */
    protected function _getConfigFileName()
    {
        return 'config.json';
    }
}
?>
