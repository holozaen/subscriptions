<?php

namespace OnlineVerkaufen\Subscriptions\Test\feature;

use OnlineVerkaufen\Subscriptions\Exception\FeatureException;
use OnlineVerkaufen\Subscriptions\Models\Feature;
use OnlineVerkaufen\Subscriptions\Models\Plan;
use OnlineVerkaufen\Subscriptions\Models\Subscription;
use OnlineVerkaufen\Subscriptions\Test\Models\Image;
use OnlineVerkaufen\Subscriptions\Test\Models\Post;
use OnlineVerkaufen\Subscriptions\Test\Models\User;
use OnlineVerkaufen\Subscriptions\Test\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class HasRestrictedUsagesTraitTest extends TestCase
{
    use RefreshDatabase;

    /** @var User $user */
    private $user;

    /** @var Subscription */
    private $subscription;

    /** @var Plan */
    private $plan;


    public function setUp(): void
    {
        parent::setUp();

        $this->user = factory(User::class)->create();
        $this->plan = factory(Plan::class)->states(['active', 'yearly'])->create();

        /** @noinspection PhpUnhandledExceptionInspection */
        $subscription = $this->user->subscribeTo($this->plan, true);
        $subscription->markAsPaid();
    }

    /** @test
     * @throws FeatureException
     */
    public function can_get_the_usage_stats_for_a_limited_model(): void
    {
        $this->plan->features()->saveMany([
            new Feature([
                'name' => 'Limited feature',
                'code' => 'only.one.post.per.user',
                'description' => 'Each user can only have one post',
                'type' => 'limit',
                'restricted_model' => User::class,
                'restricted_relation' => 'posts',
                'limit' => 1,
            ])
        ]);
        factory(Post::class)->create(['user_id' => $this->user->id]);
        $this->assertEquals([
            'used' => 1,
            'available' => 1,
            'remaining' => 0
        ], $this->user->getUsageFor('posts'));
    }

    /** @test
     * @throws FeatureException
     */
    public function can_get_the_usage_stats_for_an_unlimited_model(): void
    {
        $this->plan->features()->saveMany([
            new Feature([
                'name' => 'Unlimited feature',
                'code' => 'unlimited.images.per.user',
                'description' => 'Each user can have as many images as he likes',
                'type' => Feature::TYPE_LIMIT,
                'restricted_model' => User::class,
                'restricted_relation' => 'images',
                'limit' => 0,
            ]),
        ]);
        factory(Image::class)->create(['imageable_id' => $this->user->id, 'imageable_type' => User::class]);
        $this->assertEquals([
            'used' => 1,
            'available' => 9999,
            'remaining' => 9998
        ], $this->user->getUsageFor('images'));
    }

    /**
     * @test
     * @throws FeatureException
     */
    public function can_get_usage_stats_for_all_limited_relations(): void
    {
        $this->plan->features()->saveMany([
            new Feature([
                'name' => 'Limited feature',
                'code' => 'only.one.post.per.user',
                'description' => 'Each user can only have one post',
                'type' => 'limit',
                'restricted_model' => User::class,
                'restricted_relation' => 'posts',
                'limit' => 1,
            ]),
            new Feature([
                'name' => 'Limited feature',
                'code' => 'only.two.images.per.user',
                'description' => 'Each user can only have two images',
                'type' => 'limit',
                'restricted_model' => User::class,
                'restricted_relation' => 'images',
                'limit' => 2,
            ]),
            new Feature([
                'name' => 'Unlimited feature',
                'code' => 'unlimited.images.per.post',
                'description' => 'Each post can have a free amount of images',
                'type' => 'limit',
                'restricted_model' => Post::class,
                'restricted_relation' => 'images',
                'limit' => 0,
            ]),
        ]);
        $firstPost = factory(Post::class)->create(['user_id' => $this->user->id]);
        factory(Post::class)->create(['user_id' => $this->user->id]);
        factory(Image::class)->create(['imageable_id' => $this->user->id, 'imageable_type' => User::class]);
        factory(Image::class)->create(['imageable_id' => $this->user->id, 'imageable_type' => User::class]);
        factory(Image::class)->create(['imageable_id' => $firstPost->id, 'imageable_type' => Post::class]);
        factory(Image::class)->create(['imageable_id' => $firstPost->id, 'imageable_type' => Post::class]);
        $this->assertEquals([
            'images' =>  [
                'used' => 2,
                'available' => 2,
                'remaining' => 0
            ],
            'posts' => [
                'used' => 2,
                'available' => 1,
                'remaining' => -1
            ]
        ], $this->user->getUsages());

        $this->assertEquals([
            'images' =>  [
                'used' => 2,
                'available' => 9999,
                'remaining' => 9997
            ],
        ], $firstPost->getUsages());
    }
}
