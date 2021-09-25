<?php declare(strict_types=1);

namespace Ramsterhad\DeepDanbooruTagAssist\Application\Api\Danbooru\Service;

use Ramsterhad\DeepDanbooruTagAssist\Application\Api\Danbooru\Adapter\AdapterInterface;
use Ramsterhad\DeepDanbooruTagAssist\Application\Api\Danbooru\Exception\AdapterException;
use Ramsterhad\DeepDanbooruTagAssist\Application\Api\Danbooru\Exception\RequestPostException;
use Ramsterhad\DeepDanbooruTagAssist\Application\Api\Danbooru\Infrastructure\Repository;
use Ramsterhad\DeepDanbooruTagAssist\Framework\Container\ContainerFactory;

use function sprintf;

class DanbooruBridgeService
{
    private Repository $repository;

    public function __construct(Repository $repository)
    {
        $this->repository = $repository;
    }

    public function authenticate(string $url, string $username, string $apiKey): string
    {
        /** @var AdapterInterface $adapter */
        $adapter = ContainerFactory::getInstance()->getContainer()->get(AdapterInterface::class);

        $apiRequestUrl = sprintf(
            '%sprofile.json?login=%s&api_key=%s',
            $url,
            $username,
            $apiKey,
        );

        return $this->repository->authenticate($adapter, $apiRequestUrl);
    }

    /**
     * @throws RequestPostException
     */
    public function requestPost(string $url, string $username, string $apiKey): string
    {
        /** @var AdapterInterface $adapter */
        $adapter = ContainerFactory::getInstance()->getContainer()->get(AdapterInterface::class);

        try {
            return $this->repository->requestPost($adapter, $url, $username, $apiKey);
        } catch (AdapterException $e) {
            throw new RequestPostException();
        }
    }
}
