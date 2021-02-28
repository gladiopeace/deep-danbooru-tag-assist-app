<?php


namespace Ramsterhad\DeepDanbooruTagAssist\Application\Frontpage\Controller;


use Ramsterhad\DeepDanbooruTagAssist\Application\Api\Danbooru\Danbooru;
use Ramsterhad\DeepDanbooruTagAssist\Application\Api\MachineLearningPlatform\MachineLearningPlatform;
use Ramsterhad\DeepDanbooruTagAssist\Application\Api\MachineLearningPlatform\Picture;
use Ramsterhad\DeepDanbooruTagAssist\Application\Api\Tag\Collection;
use Ramsterhad\DeepDanbooruTagAssist\Application\Authentication\Authentication;
use Ramsterhad\DeepDanbooruTagAssist\Application\Router\Controller\Controller;

class FrontpageController extends Controller
{
    public function index(): void
    {
        if (!Authentication::isAuthenticated()) {
            return;
        }

        // Build the page
        $danbooru = new Danbooru(Danbooru::loadEndpointAddress());
        $danbooru->requestTags();

        $machineLearningPlatform = new MachineLearningPlatform();
        $machineLearningPlatform->setPicture(new Picture($danbooru->getPost()->getPicOriginal()));
        $machineLearningPlatform->requestTags();

        // List the tags from Danbooru, the ML Platform and the difference between them
        // The unknown tags are later listed and registered with the numpad keys.
        $unknownTags = $machineLearningPlatform->filterTagsFromMlpAgainstAlreadyKnownTags(
            $danbooru->getPost()->getTagCollection()
        );

        $this->assign('danbooru', $danbooru);
        $this->assign('machineLearningPlatform', $machineLearningPlatform);
        $this->assign('unknownTags', $unknownTags);
    }

    /**
     * Returns the count of \Ramsterhad\DeepDanbooruTagAssist\Application\Application::$unknownTags but limits the
     * max number by 9.
     * The information is used for the frontend to build a matrix.
     *
     * @param Collection $unknownTags
     * @param int $limit
     * @return int
     */
    public function getCountedUnknownTagsLimitedByValue(Collection $unknownTags, int $limit = 15): int
    {
        $maxTags = count($unknownTags);
        return $maxTags > $limit ? $limit : $maxTags;
    }
}