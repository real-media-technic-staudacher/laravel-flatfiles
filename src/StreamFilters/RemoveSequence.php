<?php

namespace LaravelFlatfiles\StreamFilters;

use InvalidArgumentException;
use League\Csv\AbstractCsv;

class RemoveSequence extends \php_user_filter
{
    const FILTER_NAME = 'removesequence.';

    const DELIMITER = '--';

    /**
     * The pattern to search for
     * @var string
     */
    private $pattern;

    /**
     * The string to replace the pattern with
     * @var string
     */
    private $replacement;

    /**
     * {@inheritdoc}
     */
    public function onCreate()
    {
        if (0 !== strpos($this->filtername, self::FILTER_NAME)) {
            return false;
        }

        return $this->isValidFiltername();
    }

    /**
     * Validate the filtername and set
     * the preg_replace pattern and replacement argument
     * @return bool
     */
    private function isValidFiltername()
    {
        $settings = substr($this->filtername, strlen(self::FILTER_NAME));
        $res = explode(self::DELIMITER, $settings);
        $sequence = array_shift($res);
        $delimiter = array_shift($res);
        $enclosure = array_shift($res);

        if (is_null($sequence) || is_null($enclosure) || is_null($delimiter)) {
            return false;
        }

        $this->pattern = '/(^|'.preg_quote($delimiter).')'.preg_quote($enclosure).preg_quote($sequence).'/';
        $this->replacement = '$1'.$enclosure;

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function filter($in, $out, &$consumed, $closing)
    {
        while ($bucket = stream_bucket_make_writeable($in)) {
            $bucket->data = preg_replace($this->pattern, $this->replacement, $bucket->data);
            $consumed += $bucket->datalen;
            stream_bucket_append($out, $bucket);
        }

        return PSFS_PASS_ON;
    }

    /**
     * Register the generic class stream filter
     */
    public static function registerStreamFilter()
    {
        stream_filter_register(self::FILTER_NAME.'*', self::CLASS);
    }

    /**
     * Generate the specific stream filter for a given
     * CSV class and a sequence
     *
     * @param AbstractCsv $csv      The object to which the filter will be attach
     * @param string      $sequence The sequence that will be removed from the CSV
     *
     * @throws InvalidArgumentException if the sequence contains invalid character
     * @return string
     */
    public static function createFilterName(AbstractCsv $csv, $sequence)
    {
        if (preg_match(',[\r\n\s],', $sequence)) {
            throw new InvalidArgumentException('The sequence contains invalid characters');
        }
        return self::FILTER_NAME.implode(self::DELIMITER, [$sequence, $csv->getDelimiter(), $csv->getEnclosure()]);
    }
}