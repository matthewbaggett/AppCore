<?php
namespace Segura\AppCore\Services;

use Monolog\Logger;
use Predis\Client;
use SebastianBergmann\Diff\Differ;
use Segura\Session\Session;

class EventLoggerService
{
    /**
     * These are a duplication of the Monolog levels.
     */
    const DEBUG     = 100;
    const INFO      = 200;
    const NOTICE    = 250;
    const WARNING   = 300;
    const ERROR     = 400;
    const CRITICAL  = 500;
    const ALERT     = 550;
    const EMERGENCY = 600;

    /** @var Logger */
    private $logger;
    /** @var Client */
    private $redis;
    /** @var Session */
    private $session;
    /** @var \TimeAgo */
    private $timeAgo;
    /** @var Differ */
    private $differ;

    public function __construct(
        Logger $logger,
        Client $redis,
        Session $session,
        \TimeAgo $timeAgo,
        Differ $differ
    ) {
        $this->logger  = $logger;
        $this->redis   = $redis;
        $this->session = $session;
        $this->timeAgo = $timeAgo;
        $this->differ  = $differ;
    }

    public function log(int $type, string $message, $data = [])
    {
        $time = date("Y-m-d H:i:s");
        $user = $this->session->_get("username");

        $defaultData = [
            ':user' => $user->FirstName . " " . $user->LastName,
        ];
        $data     = array_merge($defaultData, $data);
        $jsonBlob = [
            'message' => $message,
            'data'    => $data,
            'type'    => $type,
            'time'    => $time,
            'host'    => gethostname(),
            'referer' => $_SERVER['HTTP_REFERER'],
        ];
        $jsonBlob = json_encode($jsonBlob);
        $this->logger->addRecord($type, $this->translate($message, $data));
        return $this->redis->lpush("EventsLog:{$user->Username}", $jsonBlob);
    }

    public function translate($message, $replacements)
    {
        // Swap in the variables
        foreach ($replacements as $key => $value) {
            $message = str_replace($key, $value, $message);
        }
        return $message;
    }

    public function getUserHistory()
    {
        $user          = $this->session->_get("username");
        $actionHistory = [];
        foreach ($this->redis->lrange("EventsLog:{$user->Username}", 0, 1000) as $actionHistoryItem) {
            $history                    = json_decode($actionHistoryItem, true);
            $history['timeago']         = $this->timeAgo->inWords($history['time']);
            $history['hydratedMessage'] = $this->translate($history['message'], $history['data']);
            if (isset($history['data'][':before']) && isset($history['data'][':after'])) {
                $history['diff'] = $this->differ->diff($history['data'][':before'], $history['data'][':after']);
            }
            $actionHistory[] = $history;
        }
        return $actionHistory;
    }
}
