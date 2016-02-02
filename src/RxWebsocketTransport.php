<?php

namespace Thruway\Transport;

use Rx\Websocket\MessageSubject;
use Thruway\Message\Message;
use Thruway\Serializer\JsonSerializer;

class RxWebsocketTransport extends AbstractTransport
{
    /** @var MessageSubject */
    private $ms;

    /**
     * RxWebsocketTransport constructor.
     * @param MessageSubject $ms
     */
    public function __construct(MessageSubject $ms)
    {
        $this->ms = $ms;
        $this->setSerializer(new JsonSerializer());
    }

    public function sendMessage(Message $msg)
    {
        $this->ms->onNext($this->getSerializer()->serialize($msg));
    }

    /**
     * Get transport details
     *
     * @return array
     */
    public function getTransportDetails()
    {
        $transportAddress = null;
        $transportAddress = $this->ms->getRequest()->getHeader('X-RxWebsocket-Remote-Address')[0];

        $request = $this->ms->getRequest();
        $headers     = $request->getHeaders();
        $queryParams = \GuzzleHttp\Psr7\parse_query($request->getUri()->getQuery());
        $cookies     = $request->getHeader('Set-Cookie');
        $url         = $request->getRequestTarget();

        return [
            "type"             => "rxwebsocket",
            "transport_address" => $transportAddress,
            "headers"          => $headers,
            "url"              => $url,
            "query_params"     => $queryParams,
            "cookies"          => $cookies,
        ];
    }
}