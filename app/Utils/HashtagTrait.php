<?php

namespace App\Utils;

trait HashtagTrait
{
    /**
     * Filter hash tags from content
     *
     * @param string $content
     * @return array
     */
    protected function filterHashTags(string $content): array
    {
        $matches = [];
        preg_match_all('/#(\w+)\b/', $content, $matches);

        if (!isset($matches[1])) {
            return [];
        }

        $hashtags = array_values(array_unique($matches[1]));

        // Filter and validate hashtags
        $validHashtags = [];
        foreach ($hashtags as $tag) {
            if ($this->isValidHashtag($tag)) {
                $validHashtags[] = $tag;
            }
        }

        return $validHashtags;
    }

    /**
     * Validate a hashtag
     *
     * @param string $tag
     * @return bool
     */
    protected function isValidHashtag(string $tag): bool
    {
        // Hashtag should not be empty
        if (empty($tag)) {
            return false;
        }

        // Hashtag should not be too long (max 50 characters)
        if (strlen($tag) > 50) {
            return false;
        }

        // Hashtag should only contain alphanumeric characters and underscores
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $tag)) {
            return false;
        }

        // Hashtag should not start or end with underscore
        if (str_starts_with($tag, '_') || str_ends_with($tag, '_')) {
            return false;
        }

        // Hashtag should not contain only underscores
        if (preg_match('/^_+$/', $tag)) {
            return false;
        }

        return true;
    }
}
