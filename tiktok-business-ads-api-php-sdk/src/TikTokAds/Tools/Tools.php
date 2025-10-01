<?php

/**
 * Copyright 2024 Justin Stolpe.
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */
namespace TikTokAds\Tools;

// other classes we need to use
use TikTokAds\TikTokAds;

/**
 * Tools
 *
 * Get tool resources and lists.
 *     - Endpoints:
 *          - /tool/region/ GET
 *              - Docs: https://business-api.tiktok.com/portal/docs?id=1737189539571713
 *          - /tool/language/ GET
 *              - Docs: https://business-api.tiktok.com/portal/docs?id=1737188554152962
 *          - /tool/timezone/ GET
 *              - Docs: https://business-api.tiktok.com/portal/docs?id=1738308662898689
 *          - /tool/app_list/ GET
 *              - Docs: https://business-api.tiktok.com/portal/docs?id=1740040177794049
 *
 * @package     tiktok-business-ads-api-php-sdk
 * @author      Justin Stolpe
 * @link        https://github.com/jstolpe/tiktok-business-ads-api-php-sdk
 * @license     https://opensource.org/licenses/MIT
 * @version     1.0
 */
class Tools extends TikTokAds {
    /**
     * @const TikTok endpoint for the request.
     */
    const ENDPOINT = 'tool';

    /**
     * Contructor for instantiating a new object.
     *
     * @param array $config for the class.
     * @return void
     */
    public function __construct( $config ) {
        // call parent for setup
        parent::__construct( $config );
    }

    /**
     * Get regions
     *
     * @param array $params params for the request.
     * @return response.
     */
    public function getRegions( $params ) {
        return $this->get( array( // make request
            'endpoint' => '/' . self::ENDPOINT . '/region/',
            'params' => $params
        ) );
    }

    /**
     * Get languages
     *
     * @param array $params params for the request.
     * @return response.
     */
    public function getLanguages( $params ) {
        return $this->get( array( // make request
            'endpoint' => '/' . self::ENDPOINT . '/language/',
            'params' => $params
        ) );
    }

    /**
     * Get timezones
     *
     * @param array $params params for the request.
     * @return response.
     */
    public function getTimezones( $params ) {
        return $this->get( array( // make request
            'endpoint' => '/' . self::ENDPOINT . '/timezone/',
            'params' => $params
        ) );
    }

    /**
     * Get app list
     *
     * @param array $params params for the request.
     * @return response.
     */
    public function getAppList( $params ) {
        return $this->get( array( // make request
            'endpoint' => '/' . self::ENDPOINT . '/app_list/',
            'params' => $params
        ) );
    }
}

?>
