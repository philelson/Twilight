<?php
namespace philelson\Twilight;

/**
 * Class Twilight
 * @package philelson\Twilight
 * @author Phil Elson <phil@pegasus-commerce.com>
 *
 * Why do it this way rather than a cron which runs at X time and a simple version of this
 * which simply turns the group off?... simple, I was having fun.
 */
class Night extends AbstractWatcher
{
    /** config node for the 24 hour clock night hour */
    const CONFIG_ELEMENT_HOUR_OFFSET    = 'night_hour';

    /** Default lights out time 22:00 */
    const DEFAULT_NIGHT_HOUR            = 22;

    /** @var int  */
    protected $_nightHour               = self::DEFAULT_NIGHT_HOUR;

    /**
     * Turn the lights off
     */
    protected function _trigger()
    {
        $this->_group->setOn(false);
        $this->_log("Lights OFF");
        $this->_log("Exiting");
    }

    /**
     * 10PM today
     *
     * @param bool $offset
     * @return int
     */
    protected function _getThreshold($offset = true)
    {
        return strtotime('today 22:00');
    }

    /**
     * Initialise from config
     *
     * @param array $config
     * @throws \Exception
     */
    protected function _initFromConfig(array $config)
    {
        parent::_initFromConfig($config);

        $this->_nightHour = $this->_getFromConfig($config, self::CONFIG_ELEMENT_HOUR_OFFSET, self::DEFAULT_NIGHT_HOUR);

        if ($this->_nightHour < 0 || $this->_nightHour > 24) {
            throw new \Exception(sprintf("Hour (night_hour) must be between 0 and 24, %s found", $this->_nightHour));
        }

        $this->_log(sprintf("Night Hour:    '%s'", $this->_nightHour));
    }

    /**
     * @return string
     * @throws \Exception
     */
    protected function _getLoopMessage()
    {
        static $baseMessage = null;

        if (null === $baseMessage) {
            $thresholdName  = $this->getThresholdName();
            $baseMessage    = sprintf("Real %s is at '%s' ", $thresholdName, $this->_getDateString($this->_getThreshold(false)));
        }

        return $baseMessage.sprintf("time now is '%s'", $this->_getDateString());
    }

    /**
     * Relative to root of project
     *
     * @return string
     */
    protected function _getConfigFileName()
    {
        return 'night_config.json';
    }
}
?>
