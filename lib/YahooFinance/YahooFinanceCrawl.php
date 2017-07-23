<?php

namespace Dope\YahooFinance;

use DateTime;

/**
 * @category  stocks
 * @copyright Copyright (c) 2017 Dominik Peuscher
 */
class YahooFinanceCrawl extends YahooFinance
{
    const CRUMB_RETRIEVE_URL = 'https://uk.finance.yahoo.com/quote/AAPL/history';
    const DATA_RETRIEVE_URL = 'https://query1.finance.yahoo.com/v7/finance/download/%s?%s';

    /**
     * @var string
     */
    protected $crumb;

    /**
     * @var string
     */
    protected $cookie;

    /**
     * @param string $symbol
     * @param DateTime|int|null $startDate
     * @param $endDate
     * @param null $interval
     * @return array
     */
    public function getHistoricalData($symbol, $startDate = null, $endDate = null, $interval = null)
    {
        if (!isset($this->crumb) || !isset($this->cookie)) {
            $this->retrieveCrumbAndCookie(static::CRUMB_RETRIEVE_URL);
        }
        if ($startDate instanceof DateTime) {
            $startDate = $startDate->format('U');
        }
        if ($endDate instanceof DateTime) {
            $endDate = $endDate->format('U');
        }

        $result = $this->execute($symbol, $startDate, $endDate, $interval);

        $dataPoints = $this->buildResponse($result);

        return $dataPoints;
    }

    /**
     * @param $url
     * @return bool
     */
    protected function retrieveCrumbAndCookie($url)
    {
        $session = curl_init($url);
        curl_setopt($session, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($session, CURLOPT_HEADER, 1);
        $result = curl_exec($session);

        if (preg_match('/^Set-Cookie:\s*(B=[^;]*)/mi', $result, $matches)) {
            $this->cookie = $matches[1];
        }

        if (preg_match('/"CrumbStore":\{"crumb":"([^"]+)"\}/si', $result, $matches)) {
            $this->crumb = $matches[1];
        }

        return (isset($this->crumb) && isset($this->cookie));
    }

    /**
     * @param string $symbol
     * @param null|int $period1
     * @param null|int $period2
     * @param null|string $interval
     * @return mixed
     */
    protected function execute(string $symbol, $period1 = null, $period2 = null, $interval = null)
    {
        $session = curl_init($this->assemble($symbol, $period1, $period2, $interval));
        curl_setopt($session, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($session, CURLOPT_COOKIE, $this->cookie);
        return curl_exec($session);
    }

    /**
     * @param string $symbol
     * @param null|int $period1
     * @param null|int $period2
     * @param null|string $interval
     * @return string
     */
    protected function assemble(string $symbol, $period1 = null, $period2 = null, $interval = null)
    {
        if (!isset($period1) && !isset($period2)) {
            $period1 = time() - 7 * 60 * 60 * 24;
        }
        if (isset($period1) && !isset($period2)) {
            $period2 = min(time(), $period1 + 7 * 60 * 60 * 24);
        }
        if (!isset($period1) && isset($period2)) {
            $period1 = max(0, $period2 - 7 * 60 * 60 * 24);
        }
        $options = [
            'period1'  => $period1,
            'period2'  => $period2,
            'interval' => $interval??'1d',
            'events'   => 'history',
            'crumb'    => $this->crumb,
        ];
        $query_url = sprintf(static::DATA_RETRIEVE_URL, $symbol, http_build_query($options));
        return $query_url;
    }

    /**
     * @param string $result
     * @return array
     */
    protected function buildResponse($result): array
    {
        $lines = explode("\n", $result);
        $title = str_getcsv(array_shift($lines));
        $dataPoints = [];
        foreach ($lines as $line) {
            if (empty($line)) {
                continue;
            }
            $cols = str_getcsv($line);
            $dataPoint = [];
            foreach ($cols as $nr => $col) {
                $dataPoint[$title[$nr]] = $col;
            }
            $dataPoints[] = $dataPoint;
        }
        return $dataPoints;
    }
}
