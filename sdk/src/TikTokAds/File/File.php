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
namespace TikTokAds\File;

// other classes we need to use
use TikTokAds\TikTokAds;

/**
 * File
 *
 * Perform actions on files (upload images/videos).
 *     - Endpoints:
 *          - /file/image/ad/upload/ POST
 *              - Docs: https://business-api.tiktok.com/portal/docs?id=1739067433456642
 *          - /file/video/ad/upload/ POST
 *              - Docs: https://business-api.tiktok.com/portal/docs?id=1737587322856449
 *          - /file/image/ad/info/ GET
 *              - Docs: https://business-api.tiktok.com/portal/docs?id=1740051721711618
 *          - /file/video/ad/info/ GET
 *              - Docs: https://business-api.tiktok.com/portal/docs?id=1740050161973249
 *
 * @package     tiktok-business-ads-api-php-sdk
 * @author      Justin Stolpe
 * @link        https://github.com/jstolpe/tiktok-business-ads-api-php-sdk
 * @license     https://opensource.org/licenses/MIT
 * @version     1.0
 */
class File extends TikTokAds {
    /**
     * @const TikTok endpoint for the request.
     */
    const ENDPOINT = 'file';

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
     * Upload image
     *
     * @param array $params params for the request.
     * @return response.
     */
    public function uploadImage( $params ) {
        return $this->post( array( // make request
            'endpoint' => '/' . self::ENDPOINT . '/image/ad/upload/',
            'params' => $params
        ) );
    }

    /**
     * Upload video
     *
     * @param array $params params for the request.
     * @return response.
     */
    public function uploadVideo( $params ) {
        return $this->post( array( // make request
            'endpoint' => '/' . self::ENDPOINT . '/video/ad/upload/',
            'params' => $params
        ) );
    }

    /**
     * Get image info
     *
     * @param array $params params for the request.
     * @return response.
     */
    public function getImageInfo( $params ) {
        return $this->get( array( // make request
            'endpoint' => '/' . self::ENDPOINT . '/image/ad/info/',
            'params' => $params
        ) );
    }

    /**
     * Get video info
     *
     * @param array $params params for the request.
     * @return response.
     */
    public function getVideoInfo( $params ) {
        // Video IDs need to be passed as array in query string
        if (isset($params['video_ids']) && is_array($params['video_ids'])) {
            $queryParams = [
                'advertiser_id' => $params['advertiser_id'],
                'video_ids' => json_encode($params['video_ids'])
            ];
            return $this->get( array( // make request
                'endpoint' => '/' . self::ENDPOINT . '/video/ad/info/',
                'params' => $queryParams
            ) );
        }
        return $this->get( array( // make request
            'endpoint' => '/' . self::ENDPOINT . '/video/ad/info/',
            'params' => $params
        ) );
    }
}

?>
