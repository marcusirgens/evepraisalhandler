<?php

namespace MarcusIrgens\EvepraisalHandler;

use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * @covers Handler
 */
class HandlerTest extends TestCase
{

    private function successfulResponse(
        ResponseFactoryInterface $responseFactory,
        StreamFactoryInterface $streamFactory
    ): ResponseInterface {
        return $responseFactory->createResponse(200)->withBody(
            $streamFactory->createStreamFromFile("./testdata/11g8st.json")
        );
    }

    public function testHandle()
    {
        $factory = new Psr17Factory();
        $client = $this->createMock(ClientInterface::class);
        $client->method("sendRequest")->willReturn($this->successfulResponse($factory, $factory));

        $sub = new Handler(
            $factory,
            $factory,
            $factory,
            $factory,
            $client,
            "https://example.com/haul"
        );

        $req = $factory->createServerRequest("POST", "http://example.org/evepraisal")->withParsedBody(
            [
                Handler::EVEPRAISAL_PARAM => "https://evepraisal.com/a/11g8st",
            ]
        );

        $res = $sub->handle($req);
        $this->assertEquals(303, $res->getStatusCode());
        $this->assertEquals(
            "https://example.com/haul?volume=2500&collateral=525250",
            $res->getHeaderLine("location")
        );
    }
}
