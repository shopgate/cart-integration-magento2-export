<?php
/**
 * Copyright Shopgate Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 * @author    Shopgate Inc, 804 Congress Ave, Austin, Texas 78701 <interfaces@shopgate.com>
 * @copyright Shopgate Inc
 * @license   http://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
 */

namespace Shopgate\Export\Test\Unit\Model\Export;

use Magento\Framework\DataObject;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use Shopgate\Export\Model\Export\Review;

/**
 * @coversDefaultClass \Shopgate\Export\Model\Export\Review
 */
class ReviewTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var ObjectManager
     */
    private $objectManager;

    /**
     * Load object manager for initialization
     */
    public function setUp()
    {
        $this->objectManager = new ObjectManager($this);
    }

    /**
     * Creates set of DataObjects in order to fake reviews
     *
     * @param int $votePercentage
     *
     * @return DataObject
     */
    protected function getFakeReview($votePercentage)
    {
        $fakeVote = new DataObject();
        $fakeVote->setPercent($votePercentage);
        $fakeReview = new DataObject();
        $fakeReview->setRatingVotes([$fakeVote]);

        return $fakeReview;
    }

    public function scoreProvider()
    {
        return [
            '8 stars rating / 80%' => [8, 80],
            '0 stars rating / 0%' => [0, 0],
            '1 stars rating / 5%' => [1, 5],
            '0 stars rating / 1%' => [0, 1]
        ];
    }

    /**
     * @param int $expectedScore
     * @param int $scorePerentage
     *
     * @dataProvider scoreProvider
     */
    public function testScoreCalculation($expectedScore, $scorePerentage)
    {
        $fakeReview = $this->getFakeReview($scorePerentage);

        $reviewModel = new Review();
        $reviewModel->setItem($fakeReview);

        $reflection = new \ReflectionClass($reviewModel);
        $method     = $reflection->getMethod('_getScore');
        $method->setAccessible(true);
        $exportedScore = $method->invoke($reviewModel);

        $this->assertEquals($expectedScore, $exportedScore);
    }
}
