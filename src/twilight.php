<?php
namespace philelson\Twilight;

require_once '../vendor/autoload.php';

use Phue\Client;

/**
 * Class Twilight
 * @package philelson\Twilight
 * @author Phil Elson <phil@pegasus-commerce.com>
 */
class Twilight
{
    /** Default name of the config file */
    const DEFAULT_CONFIG_FILE               = 'config.json';

    /** Default delay between sunset checks */
    const DEFAULT_CHECK_DELAY_SECONDS       = 60;

    /** config node name for the hub ip address */
    const CONFIG_ELEMENT_HUB_IP             = 'hub_ip';

    /** config node name for the hub username */
    const CONFIG_ELEMENT_USERNAME           = 'username';

    /** config node name for the group of lights to be controlled */
    const CONFIG_ELEMENT_GROUP              = 'group';

    /** config node name for the verbosity 1 = file and log */
    const CONFIG_ELEMENT_VERBOSE            = 'verbose';

    /** config node name for the offset from the subset in mins */
    const CONFIG_ELEMENT_OFFSET_MINS        = 'offset_minutes';

    /** config node name for the delay in seconds between the subset check */
    const CONFIG_ELEMENT_CHECK_DELAY        = 'check_delay_seconds';

    /** URL for the subset API */
    protected $_sunsetUrl                   = 'https://api.sunrise-sunset.org/json?lat=36.7201600&lng=-4.4203400&date=today';

    /**
     * Client object
     *
     * @var null|\Phue\Client
     */
    protected $_client                      = null;

    /**
     * @var null|\Phue\Group
     */
    protected $_group                       = null;

    /**
     * Timestamp
     *
     * @var null|int
     */
    protected $_timeOfSunset                = null;

    /**
     * @var bool
     */
    protected $_verbose                     = false;

    /**
     * @var int
     */
    protected $_offsetInMinutes             = 0;

    /**
     * @var int
     */
    protected $_delayBetweenCheckSeconds    = self::DEFAULT_CHECK_DELAY_SECONDS;

    /**
     * @var array
     */
    protected $_configIndexes               = [self::CONFIG_ELEMENT_HUB_IP, self::CONFIG_ELEMENT_USERNAME, self::CONFIG_ELEMENT_GROUP];

    /**
     * Method blocks until the sunset has passed.
     *
     * @throws \Exception
     */
    public function run()
    {
        $this->_init();
        $this->_group->setOn(false);

        do {
            $this->_log(sprintf("Sunset is at '%s' time now is '%s'", $this->_getDateString($this->_timeOfSunset), $this->_getDateString()));
            sleep($this->_delayBetweenCheckSeconds);
        } while (time() < $this->_timeOfSunset);

        $this->_group->setOn(true);
        $this->_log("Lights on\nExiting");
    }

    /**
     * @throws \Exception
     */
    protected function _init()
    {
        $config = json_decode(file_get_contents(self::DEFAULT_CONFIG_FILE), true);

        foreach ($this->_configIndexes as $requiredIndex) {
            if (false === isset($config[$requiredIndex])) {
                throw new \Exception(sprintf("Config node %s is required", $requiredIndex));
            }
        }

        $hubIp                  = $config[self::CONFIG_ELEMENT_HUB_IP];
        $this->_client          = new Client($hubIp, $config[self::CONFIG_ELEMENT_USERNAME]);
        $this->_group           = $this->_getGroup($config[self::CONFIG_ELEMENT_GROUP]);
        $this->_verbose         = (bool)$this->_getFromConfig($config, self::CONFIG_ELEMENT_VERBOSE, false);
        $this->_offsetInMinutes = $this->_getFromConfig($config, self::CONFIG_ELEMENT_OFFSET_MINS, 0);
        $this->_timeOfSunset    = $this->_getSunset();
        $this->_delayBetweenCheckSeconds = $this->_getFromConfig($config, self::CONFIG_ELEMENT_CHECK_DELAY, self::DEFAULT_CHECK_DELAY_SECONDS);

        $this->_log(sprintf("Twilight started at %s", $this->_getDateString()), null);
        $this->_log(sprintf("Hub:           '%s'", $hubIp));
        $this->_log(sprintf("Sunset Offset  '%s'", $this->_offsetInMinutes));
        $this->_log(sprintf("Verbosity:     '%s'", $this->_verbose));
        $this->_log(sprintf("Delay:         '%s' seconds", $this->_delayBetweenCheckSeconds));
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
     * @return int
     * @throws \Exception
     */
    protected function _getSunset()
    {
        $data = file_get_contents($this->_sunsetUrl);
        $data = json_decode($data, true);

        if ($data['status'] != 'OK') {
            throw new \Exception("Status not ok");
        }

        return strtotime($data['results']['sunset'])+($this->_offsetInMinutes*60);
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
     */
    protected function _log($message, $flag=FILE_APPEND)
    {
        $message = (true === is_array($message)) ? print_r($message, true) : $message;

        file_put_contents('twilight.log', $message."\n", $flag);

        if (true === $this->_verbose) {
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
}

$twilight = new Twilight();
$twilight->run();
?>