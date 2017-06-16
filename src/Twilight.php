<?php
namespace philelson\Twilight;

/**
 * Class Twilight
 * @package philelson\Twilight
 * @author Phil Elson <phil@pegasus-commerce.com>
 */
class Twilight extends AbstractWatcher
{
    /** URL for the subset API */
    protected $_sunsetUrl = 'https://api.sunrise-sunset.org/json?lat=36.7201600&lng=-4.4203400&date=today';

    /**
     * @param bool $offset
     * @return int
     * @throws \Exception
     */
    protected function _getThreshold($offset=true)
    {
        static $realSunset = null;

        if (null === $realSunset) {
            $data = file_get_contents($this->_sunsetUrl);
            $data = json_decode($data, true);

            if ($data['status'] != 'OK') {
                throw new \Exception("Status not ok");
            }

            $realSunset = strtotime($data['results']['sunset']);
        }

        return (false === $offset) ? $realSunset : $realSunset+($this->_offsetInMinutes*60);
    }

    /**
     * Turn the lights on
     */
    protected function _trigger()
    {
        $this->_group->setOn(true);
        $this->_log("Lights ON");
        $this->_log("Exiting");
    }
}
?>
