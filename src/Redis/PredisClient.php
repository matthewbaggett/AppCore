<?php
namespace Gone\AppCore\Redis;

class PredisClient extends \Predis\Client
{
    public function publishEvent(Event $event)
    {
        return $this->publish(
            $event->getChannel(), 
            (string) $event
        );
    }
}