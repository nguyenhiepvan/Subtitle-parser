<?php
/**
 * Created by PhpStorm.
 * User: Hiệp Nguyễn
 * Date: 16/06/2022
 * Time: 22:55
 */


namespace Nguyenhiep\SubtitleParser;


use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

class Conversation
{
    /**
     * @var Collection
     */
    private Collection $subtitles;

    public function __construct(Collection $subtitles)
    {
        $this->subtitles = $subtitles;
    }

    //add subtitle to conversation
    public function addSubtitle(Subtitle $subtitle): void
    {
        $this->subtitles->push($subtitle);
//        $this->optimize();
    }

    // sort subtitles by start time
    protected function sort(): void
    {
        $this->subtitles = $this->subtitles->sortBy(function (Subtitle $subtitle) {
            return $subtitle->getStart();
        });
    }

    // optimize conversation by merging subtitles if possible
    protected function optimize(): void
    {
        $this->sort();
        /**
         * @var Subtitle $subtitle
         */
        foreach ($this->subtitles as $key => $subtitle) {
            /**
             * @var Subtitle $_subtitle
             */
            foreach ($this->subtitles as $_key => $_subtitle) {
                if ($subtitle->shouldMerge($_subtitle)) {
                    $subtitle->merge($_subtitle);
                    $this->subtitles->forget($_key);
                    $this->subtitles->put($key, $subtitle);
                }
            }
        }
    }

    // get best subtitle with start time nearest to $start and end time nearest to $end
    public function getBestSubtitle(int $start, int $end): ?Subtitle
    {
        $subtitle = $this->subtitles->first(function (Subtitle $subtitle) use ($start, $end) {
            return $subtitle->getStart() <= $start && $subtitle->getEnd() >= $end;
        });
        if ($subtitle === null) {
            $subtitle = $this->subtitles->first(function (Subtitle $subtitle) use ($start, $end) {
                return $subtitle->getStart() >= $start && $subtitle->getEnd() <= $end;
            });
        }
        return $subtitle;
    }

    public static function parse(string $text, string $lang = null): Conversation
    {
        $subtitles = new Collection();
        $lines     = explode("\n", $text);
        for ($i = 0, $iMax = count($lines); $i < $iMax; $i++) {
            $line = Arr::get($lines, $i, "");
            $line = trim($line);
            if (empty($line)) {
                continue;
            }
            if (str_contains($line, "-->")) {
                // parse start and end time
                $parts = explode(' --> ', $line);
                if (count($parts) !== 2) {
                    throw new \RuntimeException('Invalid time format');
                }
                [$start, $end] = $parts;
                $text = "";
                // append text until next line is index
                for ($z = $i + 1; $z < $iMax; $z++) {
                    $line = Arr::get($lines, $z, "");
                    $line = trim($line);
                    if (empty($line)) {
                        continue;
                    }
                    if (preg_match("/^\d*$/u", $line)) {
                        break;
                    }
                    $text .= strip_tags($line);
                }
                // add subtitle
                //TODO: detect language
                $subtitles->push(new Subtitle($start, $end, $text, $lang));
            }
        }
        return new Conversation($subtitles);
    }

    // get all subtitles
    public function getSubtitles(): Collection
    {
        return $this->subtitles;
    }

    // get subtitle at index $index
    public function getSubtitle(int $index): ?Subtitle
    {
        return $this->subtitles->get($index);
    }

    // get number of subtitles
    public function count(): int
    {
        return $this->subtitles->count();
    }

    // generator for subtitles
    public function getIterator(): \Generator
    {
        /**
         * @var Subtitle $subtitle
         */
        foreach ($this->subtitles as $key => $subtitle) {
            yield $key => $subtitle;
        }
    }

    // update item in subtitles
    public function updateSubtitle(Subtitle $subtitle, int $key = null): void
    {
        //find index of subtitle
        $index = $key ?? $this->subtitles->search($subtitle);
        if ($index === false) {
            throw new \RuntimeException('Subtitle not found');
        }
        $this->subtitles->put($index, $subtitle);
    }

    // get all subtitles has start time or end time between $start and $end
    public function getSubtitlesBetween(int $start, int $end): Conversation
    {
        $subtitles = $this->subtitles->filter(function (Subtitle $subtitle) use ($start, $end) {
            return ($subtitle->getStart() >= $start && $subtitle->getStart() <= $end) || ($subtitle->getEnd() >= $start && $subtitle->getEnd() <= $end);
        });
        return new Conversation($subtitles);
    }

    // merge all subtitles of conversation into one subtitle
    public function merge(): ?Subtitle
    {
        $this->sort();
        $merged = $this->subtitles->first();
        if (!$merged) {
            return null;
        }
        for ($i = 1, $iMax = $this->count(); $i < $iMax; $i++) {
            $subtitle = $this->getSubtitle($i);
            if ($subtitle) {
                $merged->merge($subtitle);
            }
        }
        return $merged;
    }

    //compare conversation with self
    public function compare(Conversation $conversation): void
    {
        /**
         * @var Subtitle $subtitle
         */
        foreach ($this->getIterator() as $key => $subtitle) {
            $_conversation = $conversation->getSubtitlesBetween($subtitle->getStart(), $subtitle->getEnd());
            if ($_conversation->count() > 0) {
                $_subtitle = $_conversation->merge();
                if ($_subtitle) {
                    $subtitle->addSynonym($_subtitle);
                    $this->updateSubtitle($subtitle, $key);
                }
            }
        }
    }

}