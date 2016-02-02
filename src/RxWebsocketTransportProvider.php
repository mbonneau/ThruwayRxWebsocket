<?php

namespace Thruway\Transport;

use Rx\DisposableInterface;
use Rx\Observer\CallbackObserver;
use Rx\Websocket\MessageSubject;
use Rx\Websocket\Server;
use Thruway\Event\ConnectionCloseEvent;
use Thruway\Event\ConnectionOpenEvent;
use Thruway\Event\RouterStartEvent;
use Thruway\Event\RouterStopEvent;
use Thruway\Logging\Logger;
use Thruway\Session;

class RxWebsocketTransportProvider extends AbstractRouterTransportProvider
{
    /** @var string */
    private $bindAddress;

    /** @var int */
    private $port;

    /** @var DisposableInterface */
    private $serverDisposable;

    /** @var Session[] */
    private $sessions = [];

    /**
     * RxWebsocketTransportProvider constructor.
     */
    public function __construct($bindAddress = '127.0.0.1', $port = 9090)
    {
        $this->bindAddress = $bindAddress;
        $this->port = $port;
    }

    public function createNewSessionForMessageSubject(MessageSubject $ms) {
        $transport = new RxWebsocketTransport($ms);

        $session = $this->router->createNewSession($transport);

        $this->router->getEventDispatcher()->dispatch('connection_open', new ConnectionOpenEvent($session));

        $ms->subscribe(new CallbackObserver(
            function ($message) use ($transport, $session) {
                $thruwayMessage = $transport->getSerializer()->deserialize($message);

                $session->dispatchMessage($thruwayMessage);
            },
            function (\Exception $err) use ($session) {
                Logger::error($this, "Error in message callback");
                $this->router->getEventDispatcher()->dispatch('connection_close', new ConnectionCloseEvent($session));
            },
            function () use ($session) {
                Logger::info($this, "MessageSubject completed");
                $this->router->getEventDispatcher()->dispatch('connection_close', new ConnectionCloseEvent($session));
            }
        ));
    }

    public function handleRouterStart(RouterStartEvent $event)
    {
        $server = new Server($this->bindAddress, $this->port, false, ["wamp.2.json"]);

        Logger::info($this, "Websocket listening on ".$this->bindAddress.":".$this->port);

        $this->serverDisposable = $server->subscribe(new CallbackObserver(
            function (MessageSubject $ms) {
                $this->createNewSessionForMessageSubject($ms);
            },
            function (\Exception $err) {
                Logger::error($this, "Received error on server: " . $err->getMessage());
            },
            function () {
                Logger::alert($this, "Completed. Not sure if we should ever do that.");
            }
        ));
    }

    public function handleRouterStop(RouterStopEvent $event)
    {
        foreach ($this->sessions as $k) {
            $this->sessions[$k]->shutdown();
        }

        if ($this->serverDisposable) {
            $this->serverDisposable->dispose();
        }
    }

    public static function getSubscribedEvents()
    {
        return [
            "router.start" => ["handleRouterStart", 10],
            "router.stop"  => ["handleRouterStop", 10]
        ];
    }
}