<?php
/**
 * Created by PhpStorm.
 * User: Hiệp Nguyễn
 * Date: 16/06/2022
 * Time: 17:10
 */


namespace Nguyenhiep\SubtitleParser;


use Illuminate\Support\Collection;
use JetBrains\PhpStorm\ArrayShape;

class Subtitle
{

    private int $start;

    private int $end;

    private string $text;

    private Collection $synonyms;

    public function __construct(string $start, string $end, string $text, string $lang = null)
    {
        $this->start    = $this->parseTime($start);
        $this->end      = $this->parseTime($end);
        $this->text     = $text;
        $this->lang     = $lang;
        $this->synonyms = new Collection();
    }

    // add subtitle to synonyms
    public function addSynonym(Subtitle $synonym): void
    {
        $this->synonyms->push($synonym);
    }

    private function parseTime(string $time): int
    {
        $parts = explode(':', $time);
        if (count($parts) !== 3) {
            throw new \RuntimeException('Invalid time format');
        }
        [$hours, $minutes, $seconds] = $parts;
        [$seconds, $milliseconds] = explode(",", $seconds);
        return ((int)$milliseconds) + ((int)$seconds) * 1000 + ((int)$minutes * 60 * 1000) + ((int)$hours * 3600 * 1000);
    }

    //merge subtitle to self
    public function merge(Subtitle $subtitle): void
    {
        $this->end   = max($this->end, $subtitle->end);
        $this->start = min($this->start, $subtitle->start);
        $this->text  .= PHP_EOL . $subtitle->getText();
        $this->synonyms->push($subtitle->getSynonyms());
    }

    // should merge subtitle to self if start time between self start and end time or end time between self start and end time
    public function shouldMerge(Subtitle $subtitle): bool
    {
        return ($this->start <= $subtitle->start && $subtitle->start <= $this->end) || ($this->start <= $subtitle->end && $subtitle->end <= $this->end);
    }

    // getter and setter
    public function getStart(): int
    {
        return $this->start;
    }

    public function getEnd(): int
    {
        return $this->end;
    }

    public function getText(): string
    {
        return $this->text;
    }

    public function getSynonyms(): Collection
    {
        return $this->synonyms;
    }

    public function getLang(): string
    {
        return $this->lang;
    }

    public function setLang(string $lang): void
    {
        $this->lang = $lang;
    }

    // to array
    #[ArrayShape(['start' => "int", 'end' => "int", 'text' => "string", 'synonyms' => "array"])]
    public function __toArray(): array
    {
        return [
            'start'    => $this->start,
            'end'      => $this->end,
            'text'     => $this->text,
            'synonyms' => $this->synonyms->toArray()
        ];
    }

    public function __toString(): string
    {
        $synonyms = "";
        /**
         * @var Subtitle $synonym
         */
        foreach ($this->synonyms as $synonym) {
            $synonyms .= $synonym->getLang() . ":" . preg_replace("/\r|\n/", "", $synonym->getText()) . PHP_EOL;
        }
        $text = $this->getLang() . ":" . preg_replace("/\r|\n/", "", $this->text);
        return <<<TEXT
$text
$synonyms
--------------------------------------------------------------------------------
TEXT;

    }

}