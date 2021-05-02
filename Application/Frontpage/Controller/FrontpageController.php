<?php declare(strict_types=1);


namespace Ramsterhad\DeepDanbooruTagAssist\Application\Frontpage\Controller;



use Ramsterhad\DeepDanbooruTagAssist\Application\Api\Danbooru\Danbooru;
use Ramsterhad\DeepDanbooruTagAssist\Application\Api\Danbooru\Endpoint;
use Ramsterhad\DeepDanbooruTagAssist\Application\Api\Danbooru\Picture;
use Ramsterhad\DeepDanbooruTagAssist\Application\Api\Danbooru\Post;
use Ramsterhad\DeepDanbooruTagAssist\Application\Api\Danbooru\Tag;
use Ramsterhad\DeepDanbooruTagAssist\Application\Api\MachineLearningPlatform\MachineLearningPlatform;
use Ramsterhad\DeepDanbooruTagAssist\Application\Api\PredictedTagsDatabase\Exception\PredictedTagsDatabaseInvalidResponseException;
use Ramsterhad\DeepDanbooruTagAssist\Application\Api\PredictedTagsDatabase\PredictedTagsDatabase;
use Ramsterhad\DeepDanbooruTagAssist\Application\Api\Tag\TagCollection;
use Ramsterhad\DeepDanbooruTagAssist\Application\Authentication\Authentication;
use Ramsterhad\DeepDanbooruTagAssist\Application\Router\Controller\Contract\Controller;
use Ramsterhad\DeepDanbooruTagAssist\Application\Configuration\Config;
use Ramsterhad\DeepDanbooruTagAssist\Application\Router\Controller\Response;
use Ramsterhad\DeepDanbooruTagAssist\Application\Router\Router;
use Ramsterhad\DeepDanbooruTagAssist\Application\System\StringUtils;


class FrontpageController implements Controller
{
    public function index(): Response
    {
        if (!Authentication::isAuthenticated()) {
            Router::route('auth');
        }

        // Build the page
        $danbooru = new Danbooru();
        $danbooru->requestTags(
            new TagCollection(),
            new Post(),
            new Endpoint()
        );

        $picture = new Picture($danbooru->getPost()->getPicOriginal());
        $picture->download();
        $picture->calculateDominantColors();

        // Try to use pre predicted tags. If something fails with the database, use the machine learning platform api.
        try {
            $predictedTagsDatabase = new PredictedTagsDatabase();
            $predictedTagsDatabase->requestTags(
                $danbooru->getPost()->getId(),
                new \Ramsterhad\DeepDanbooruTagAssist\Application\Api\PredictedTagsDatabase\Endpoint()
            );

            $suggestedTags = $predictedTagsDatabase->getCollection();

        } catch (PredictedTagsDatabaseInvalidResponseException $ex) {

            $machineLearningPlatform = new MachineLearningPlatform();
            $machineLearningPlatform->setPicture($picture);
            $machineLearningPlatform->requestTags();

            $suggestedTags = $machineLearningPlatform->getCollection();

        } finally {
            $picture->delete();
        }

        // Removes the rating tags.
        $suggestedTags = $this->filterSafeTags($suggestedTags);

        // Filter the known tags from Danbooru against the suggested tags and return the difference.
        // The unknown tags are later listed and registered with the numpad keys.
        $unknownTags = $this->filterTagsAgainstAlreadyKnownTags($suggestedTags, $danbooru->getCollection());

        $response = new Response($this, 'Frontpage.frontpage.index');
        $response->assign('danbooru', $danbooru);
        $response->assign('suggestedTags', $suggestedTags);
        $response->assign('picture', $picture);
        $response->assign('unknownTags', $unknownTags);
        $response->assign('danbooruApiUrl', Config::get('danbooru_api_url'));
        $response->assign('suggestedTagsLimit', (int) Config::get('limit_for_suggested_tags'));

        return $response;
    }

    /**
     * This function compares the Danbooru tags with given ones and returns a tag collection of unknown tags.
     *
     * @param TagCollection $collection
     * @return TagCollection
     *
     */
    public function filterTagsAgainstAlreadyKnownTags(
        TagCollection $suggestedTags,
        TagCollection $danbooruTags
    ): TagCollection {
        $unknownTagCollection = new TagCollection();

        foreach ($suggestedTags->getTags() as $tag) {

            $knownTag = false; //Unknown by default, unless proven known

            foreach ($danbooruTags->getTags() as $danbooruTag) {

                if (trim($danbooruTag->getName()) === trim($tag->getName())) {
                    // Tag is already known on danbooru:
                    $knownTag = true;
                    continue;
                }
            }

            // Add unknown (!known) tags to $unknownTagCollection
            if (!$knownTag) {
                $unknownTagCollection->add($tag);
            }
        }

        return $unknownTagCollection;
    }

    /**
     * Filters the rating tags from the result.
     *
     * @param TagCollection $collection
     * @return TagCollection
     */
    public function filterSafeTags(TagCollection $collection): TagCollection
    {
        $blacklist = [
            'rating:s',
            'rating:safe',
            'rating:q',
            'rating:questionable',
            'rating:e',
            'rating:explicit',
        ];

        $filtered = new TagCollection();

        foreach ($collection->getTags() as $tag) {
            if (StringUtils::strposArray($tag->getName(), $blacklist) === false) {
                $filtered->add($tag);
            }
        }

        return $filtered;
    }

    /**
     * Returns the count of \Ramsterhad\DeepDanbooruTagAssist\Application\Application::$unknownTags but limits the
     * max number by 9.
     * The information is used for the frontend to build a matrix.
     *
     * @param TagCollection $unknownTags
     * @param int $limit
     * @return int
     */
    public function getCountedUnknownTagsLimitedByValue(TagCollection $unknownTags, int $limit = 15): int
    {
        $maxTags = count($unknownTags);
        return $maxTags > $limit ? $limit : $maxTags;
    }

    /**
     * @param TagCollection $suggestedTags All suggested tags
     * @param TagCollection $filteredTags Reduced filter collection from the machine learning platform,
     *                                    reduced by Danbooru tags.
     *
     * @return string
     */
    public function tagsCssClassHelperUnknownTags(TagCollection $suggestedTags, TagCollection $filteredTags): string
    {
        $mlpTagList = '';

        foreach ($suggestedTags->getTags() as $tag) {
            $isNew = false;

            foreach ($filteredTags->getTags() as $filteredTag) {
                if ($tag->getName() === $filteredTag->getName()) {
                    $isNew = true;
                    continue;
                }
            }

            if ($isNew) {
                $mlpTagList .= '<span class="tag unknownTag">' .$tag->getName() . '</span>';
            } else {
                $mlpTagList .= '<span class="tag">' . $tag->getName() . '</span>';
            }
        }

        return $mlpTagList;
    }

    public function tagsCssClassHelperColoredDanbooruTags(Tag $tag): string
    {
        return '<span style="color: '. $tag->getHexColor().';">'.$tag->getName().'</span>';
    }
}