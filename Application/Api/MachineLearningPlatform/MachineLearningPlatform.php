<?php declare(strict_types=1);

namespace Ramsterhad\DeepDanbooruTagAssist\Application\Api\MachineLearningPlatform;


use Ramsterhad\DeepDanbooruTagAssist\Application\Api\ApiContract;
use Ramsterhad\DeepDanbooruTagAssist\Application\Api\Tag\Collection;
use Ramsterhad\DeepDanbooruTagAssist\Application\Api\Tag\Tag;
use Ramsterhad\DeepDanbooruTagAssist\Application\Configuration\Config;
use Ramsterhad\DeepDanbooruTagAssist\Application\System\StringUtils;

class MachineLearningPlatform implements ApiContract
{
    private Collection $collection;

    private Picture $picture;

    public function setPicture(Picture $picture): void
    {
        $this->picture = $picture;
    }

    /**
     * The Tensorflow-based RESnet runs on WSL (or native linux). The code as-is is suitable for running on Windows
     * with WSL but can be adapted to run on native linux
     * The Windows image paths is converted to wsl
     * Example: C:\path\to\picture.jpg becomes /mnt/c/path/to/picture.jpg
     */
    private function preparePictureStoragePath(): string
    {
        // Obtain full path
        $pathToPicture = $this->picture->getFullPathToFile();
        // Convert C:\ to /mnt/C/ (note: returns upper-case drive letter)
        $pathToPicture = preg_replace('/(\w):\\\\/', '/mnt/$1/', $pathToPicture);
        // Convert \ to /
        $pathToPicture = str_replace('\\', '/', $pathToPicture);
        // Convert any residual uppercase to lowercase
        $pathToPicture = strtolower($pathToPicture);
        // The above may fail if the image is stored in a path with a capital letter
        return $pathToPicture;
    }

    /**
     * The function triggers an analyse by an machine learning platform. The call returns a simpel array with multiple
     * entries, but not only tags, so we have to filter the output accordingly. It also filters specific tags
     * by blacklist.
     */
    public function requestTags(): void
    {
        $this->picture->download();

        if (Config::get('machine_learning_platform_repository_debug') === 'false') {
            $wsdlCompatiblePictureStoragePath = $this->preparePictureStoragePath();
            //
            // ml.sh accepts the path to a JPG or PNG image, and a certainty threshold. Tags with a certainty
            // lower than the threshold are not returned. The recognized tags will be returned to $output
            // 
            exec("wsl bash ~/ml.sh '".$wsdlCompatiblePictureStoragePath."' '0.500'", $output, $retval);
        } else {
            // Placeholder array, replaces the above exec()
            $output = [
                'Tags of /mnt/f/deepdanbooru/sample-460c5595dcf9b07d58f951d349202d98.jpg:',
                '(0.997) 1girl',
                '(0.706) bangs',
                '(0.840) blurry',
                '(0.943) bow',
                '(0.640) brown_eyes',
                '(0.968) brown_hair',
                '(0.826) eyebrows_visible_through_hair',
                '(0.548) frilled_bow',
                '(0.753) frills',
                '(0.905) hair_bow',
                '(0.990) hair_tubes',
                '(0.596) long_hair',
                '(0.904) looking_at_viewer',
                '(0.731) red_bow',
                '(0.992) solo',
                '(1.000) hakurei_reimu',
                '(1.000) rating:safe',
                '',
            ];
        }

        // Tag blacklist
        // The majority of the tags used to train the resNET were SFW. This model is therefore not suitable for
        // accurate assessment of s/q/e status
        $blacklist = [
            'rating:s',
            'rating:q',
            'rating:e',
        ];

        $tags = [];
        foreach ($output as $item) {

            // Filters for ( as we only want tags with a score.
            if (strpos($item, '(') !== false && !StringUtils::strposArray($item, $blacklist) !== false) {
                $item = preg_split('/ /', $item);
                $item[0] = str_replace(['(', ')'], '', $item[0]);
                $tags[] = $item;
            }
        }

        arsort($tags);
        $this->collection = new Collection();

        foreach ($tags as $tag) {
            $this->collection->add(new Tag($tag[1], $tag[0]));
        }

        $this->picture->delete();
    }

    public function getCollection(): Collection
    {
        return $this->collection;
    }

    /**
     *
     * This function compares the Danbooru tags with the found ones from the MLP.
     * Only unknown tags, like found tags by the MLP which are not listed at Danbooru are returned.
     *
     * @param Collection $tagsDanbooru
     * @return Collection
     *
     */
    public function filterTagsFromMlpAgainstAlreadyKnownTags(Collection $tagsDanbooru): Collection
    {
        $unknownTagCollection = new Collection();

        foreach ($this->collection->getTags() as $tag) {

            $knownTag = false; //Unknown by default, unless proven known

            foreach ($tagsDanbooru->getTags() as $danbooruTag) {

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
}