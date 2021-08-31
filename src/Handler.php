<?php

namespace MarcusIrgens\EvepraisalHandler;

use Http\Message\UriFactory;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as ServerRequest;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\UriFactoryInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Handles Evepraisal requests for hauling apps
 */
class Handler implements RequestHandlerInterface, LoggerAwareInterface
{
    private const VALUE_MODIFIER = 1.1;
    public const EVEPRAISAL_PARAM = "evepraisal_url";
    private const EVEPRAISAL_PATTERN = '/evepraisal\.com\/a\/(?P<id>[a-z0-9]{2,32})/';
    private LoggerInterface $logger;

    public function __construct(
        private RequestFactoryInterface $requestFactory,
        private ResponseFactoryInterface $responseFactory,
        private StreamFactoryInterface $streamFactory,
        private UriFactoryInterface $uriFactory,
        private ClientInterface $client,
        private string $callbackUrl,
        ?LoggerInterface $logger = null,
        private $valueMod = self::VALUE_MODIFIER,
    ) {
        $this->responseFactory = $responseFactory;
        $this->streamFactory = $streamFactory;
        $this->callbackUrl = $callbackUrl;
        $this->logger = $logger ?? new NullLogger();
    }

    public function handle(ServerRequest $request): Response
    {
        $response = $this->responseFactory->createResponse();

        $res = $request->getParsedBody();
        if (!is_array($res)) {
            return $response->withStatus(400)->withBody($this->streamFactory->createStream("Bad request"));
        }

        $url = $res[self::EVEPRAISAL_PARAM];
        if (!preg_match(self::EVEPRAISAL_PATTERN, $url, $matches)) {
            return $response->withStatus(400)->withBody($this->streamFactory->createStream("Invalid Evepraisal URL"));
        }

        $id = $matches["id"];

        try {
            $data = $this->getEvepraisalData($id);
        } catch (Throwable $t) {
            $this->logger->error("Evepraisal fetch failed", ["exception" => $t]);
            return $response->withStatus(500)
                ->withBody($this->streamFactory->createStream("Could not send Evepraisal request"));
        }

        $uri = $this->uriFactory->createUri($this->callbackUrl)->withQuery(http_build_query([
            "volume" => ceil($data["volume"]),
            "collateral" => ceil((float)$data["sell"] * (float)$this->valueMod)
        ]));

        return $response->withStatus(303)->withHeader("location", $uri->__toString());
    }

    // getEvepraisalData returns the totals data
    private function getEvepraisalData(string $id): array
    {
        $url = sprintf("https://evepraisal.com/a/%s.json", $id);
        $req = $this->requestFactory->createRequest("POST", $url);
        $res = $this->client->sendRequest($req);
        $data = json_decode($res->getBody()->getContents(), true, 512, JSON_THROW_ON_ERROR);
        if (!array_key_exists("totals", $data) || !is_array($data["totals"])) {
            throw new Exception("Totals not present in API response");
        }

        // todo: verify contents of this array
        return $data["totals"];
    }

    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }
}
