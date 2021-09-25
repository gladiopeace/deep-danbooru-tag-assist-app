<?php declare(strict_types=1);

namespace Ramsterhad\DeepDanbooruTagAssist\Application\Api\Danbooru\Adapter;

use Ramsterhad\DeepDanbooruTagAssist\Application\Api\Danbooru\Exception\AdapterException;

interface AdapterInterface
{
    public function init(): AdapterInterface;

    public function sendTo(string $url): AdapterInterface;

    public function byMethod(string $method): AdapterInterface;

    public function authenticateWith(string $username, string $password): AdapterInterface;

    public function sendData(array $data): AdapterInterface;

    public function requestTransferStatus(bool $requestIt): AdapterInterface;

    public function waitForFirstByte(int $seconds): AdapterInterface;

    public function waitForFinishingTheRequest(int $seconds): AdapterInterface;

    /**
     * @throws AdapterException
     */
    public function execute(): AdapterInterface;

    public function hangUp(): AdapterInterface;

    public function getResponse(): string;
}