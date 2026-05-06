<?php

namespace Database\Seeders;

use App\Models\Research\Niche;
use Illuminate\Database\Seeder;

/**
 * Seeds the curated niche taxonomy used by NicheClassificationService.
 * 12 top-level verticals, ~10 children each (~132 rows). Idempotent via
 * `slug` upsert so re-running is safe and won't duplicate user-edited
 * is_dynamic=true rows added by DiscoverEmergingNichesJob (those have
 * different slugs prefixed with `dyn-`).
 */
class NicheTaxonomySeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->taxonomy() as $vertical) {
            $parent = Niche::updateOrCreate(
                ['slug' => $vertical['slug']],
                [
                    'name' => $vertical['name'],
                    'parent_id' => null,
                    'is_dynamic' => false,
                    'is_approved' => true,
                ]
            );

            foreach ($vertical['children'] as $childSlug => $childName) {
                Niche::updateOrCreate(
                    ['slug' => $childSlug],
                    [
                        'name' => $childName,
                        'parent_id' => $parent->id,
                        'is_dynamic' => false,
                        'is_approved' => true,
                    ]
                );
            }
        }
    }

    /** @return array<int, array{slug: string, name: string, children: array<string, string>}> */
    private function taxonomy(): array
    {
        return [
            [
                'slug' => 'health-wellness',
                'name' => 'Health & Wellness',
                'children' => [
                    'fitness' => 'Fitness',
                    'nutrition' => 'Nutrition',
                    'supplements' => 'Supplements',
                    'mental-health' => 'Mental Health',
                    'yoga' => 'Yoga',
                    'sleep' => 'Sleep',
                    'weight-loss' => 'Weight Loss',
                    'womens-health' => 'Women\'s Health',
                    'mens-health' => 'Men\'s Health',
                    'chronic-conditions' => 'Chronic Conditions',
                ],
            ],
            [
                'slug' => 'finance-money',
                'name' => 'Finance & Money',
                'children' => [
                    'personal-finance' => 'Personal Finance',
                    'investing' => 'Investing',
                    'cryptocurrency' => 'Cryptocurrency',
                    'mortgages' => 'Mortgages',
                    'retirement-planning' => 'Retirement Planning',
                    'insurance' => 'Insurance',
                    'taxes' => 'Taxes',
                    'credit-debt' => 'Credit & Debt',
                    'side-hustles' => 'Side Hustles',
                    'real-estate-investing' => 'Real Estate Investing',
                ],
            ],
            [
                'slug' => 'technology',
                'name' => 'Technology',
                'children' => [
                    'saas' => 'SaaS',
                    'devtools' => 'DevTools',
                    'ai-machine-learning' => 'AI & Machine Learning',
                    'cybersecurity' => 'Cybersecurity',
                    'cloud-computing' => 'Cloud Computing',
                    'web-development' => 'Web Development',
                    'mobile-apps' => 'Mobile Apps',
                    'hardware-gadgets' => 'Hardware & Gadgets',
                    'programming-languages' => 'Programming Languages',
                    'databases' => 'Databases',
                ],
            ],
            [
                'slug' => 'marketing-business',
                'name' => 'Marketing & Business',
                'children' => [
                    'marketing-saas' => 'Marketing SaaS',
                    'seo' => 'SEO',
                    'content-marketing' => 'Content Marketing',
                    'social-media-marketing' => 'Social Media Marketing',
                    'email-marketing' => 'Email Marketing',
                    'paid-ads' => 'Paid Ads',
                    'ecommerce' => 'E-commerce',
                    'b2b-sales' => 'B2B Sales',
                    'entrepreneurship' => 'Entrepreneurship',
                    'marketing-analytics' => 'Marketing Analytics',
                ],
            ],
            [
                'slug' => 'lifestyle-home',
                'name' => 'Lifestyle & Home',
                'children' => [
                    'home-improvement' => 'Home Improvement',
                    'gardening' => 'Gardening',
                    'interior-design' => 'Interior Design',
                    'cleaning-organizing' => 'Cleaning & Organizing',
                    'smart-home' => 'Smart Home',
                    'cooking' => 'Cooking',
                    'baking' => 'Baking',
                    'coffee-tea' => 'Coffee & Tea',
                    'pets' => 'Pets',
                    'sustainable-living' => 'Sustainable Living',
                ],
            ],
            [
                'slug' => 'travel',
                'name' => 'Travel',
                'children' => [
                    'adventure-travel' => 'Adventure Travel',
                    'budget-travel' => 'Budget Travel',
                    'luxury-travel' => 'Luxury Travel',
                    'family-travel' => 'Family Travel',
                    'solo-travel' => 'Solo Travel',
                    'cruises' => 'Cruises',
                    'road-trips' => 'Road Trips',
                    'travel-gear' => 'Travel Gear',
                    'destinations-europe' => 'Destinations — Europe',
                    'destinations-asia' => 'Destinations — Asia',
                ],
            ],
            [
                'slug' => 'education-career',
                'name' => 'Education & Career',
                'children' => [
                    'online-learning' => 'Online Learning',
                    'career-development' => 'Career Development',
                    'resume-job-search' => 'Resume & Job Search',
                    'productivity' => 'Productivity',
                    'study-skills' => 'Study Skills',
                    'languages' => 'Languages',
                    'test-prep' => 'Test Prep',
                    'coding-bootcamps' => 'Coding Bootcamps',
                    'soft-skills' => 'Soft Skills',
                    'remote-work' => 'Remote Work',
                ],
            ],
            [
                'slug' => 'parenting-family',
                'name' => 'Parenting & Family',
                'children' => [
                    'pregnancy' => 'Pregnancy',
                    'newborn-care' => 'Newborn Care',
                    'toddlers' => 'Toddlers',
                    'school-age-kids' => 'School-Age Kids',
                    'teens' => 'Teens',
                    'homeschooling' => 'Homeschooling',
                    'parenting-styles' => 'Parenting Styles',
                    'family-activities' => 'Family Activities',
                    'maternity' => 'Maternity',
                    'fatherhood' => 'Fatherhood',
                ],
            ],
            [
                'slug' => 'fashion-beauty',
                'name' => 'Fashion & Beauty',
                'children' => [
                    'mens-fashion' => 'Men\'s Fashion',
                    'womens-fashion' => 'Women\'s Fashion',
                    'skincare' => 'Skincare',
                    'makeup' => 'Makeup',
                    'hair-care' => 'Hair Care',
                    'fragrance' => 'Fragrance',
                    'sustainable-fashion' => 'Sustainable Fashion',
                    'streetwear' => 'Streetwear',
                    'luxury-fashion' => 'Luxury Fashion',
                    'fashion-trends' => 'Fashion Trends',
                ],
            ],
            [
                'slug' => 'entertainment-hobbies',
                'name' => 'Entertainment & Hobbies',
                'children' => [
                    'gaming' => 'Gaming',
                    'books-reading' => 'Books & Reading',
                    'movies-tv' => 'Movies & TV',
                    'music' => 'Music',
                    'photography' => 'Photography',
                    'crafts-diy' => 'Crafts & DIY',
                    'board-games' => 'Board Games',
                    'outdoor-hobbies' => 'Outdoor Hobbies',
                    'collectibles' => 'Collectibles',
                    'podcasts' => 'Podcasts',
                ],
            ],
            [
                'slug' => 'food-drink',
                'name' => 'Food & Drink',
                'children' => [
                    'recipes' => 'Recipes',
                    'restaurants' => 'Restaurants',
                    'diets' => 'Diets',
                    'beverages' => 'Beverages',
                    'wine' => 'Wine',
                    'beer-spirits' => 'Beer & Spirits',
                    'vegan-vegetarian' => 'Vegan & Vegetarian',
                    'meal-prep' => 'Meal Prep',
                    'food-trends' => 'Food Trends',
                    'kitchen-equipment' => 'Kitchen Equipment',
                ],
            ],
            [
                'slug' => 'sports-outdoors',
                'name' => 'Sports & Outdoors',
                'children' => [
                    'running' => 'Running',
                    'cycling' => 'Cycling',
                    'hiking' => 'Hiking',
                    'camping' => 'Camping',
                    'fishing' => 'Fishing',
                    'hunting' => 'Hunting',
                    'team-sports' => 'Team Sports',
                    'combat-sports' => 'Combat Sports',
                    'water-sports' => 'Water Sports',
                    'winter-sports' => 'Winter Sports',
                ],
            ],
        ];
    }
}
