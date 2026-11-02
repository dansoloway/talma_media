<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Tag;
use App\Models\TagCategory;
use Illuminate\Database\Seeder;

class TagTaxonomySeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->taxonomy() as $categoryData) {
            $tags = $categoryData['tags'];
            unset($categoryData['tags']);

            $category = TagCategory::query()->updateOrCreate(
                ['key' => $categoryData['key']],
                $categoryData,
            );

            foreach ($tags as $tag) {
                Tag::query()->updateOrCreate(
                    [
                        'category_id' => $category->id,
                        'value' => $tag['value'],
                    ],
                    [
                        'label_en' => $tag['label_en'],
                        'label_he' => $tag['label_he'],
                        'synonyms' => $tag['synonyms'] ?? null,
                    ],
                );
            }
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function taxonomy(): array
    {
        return [
            [
                'key' => 'people',
                'label_en' => 'People',
                'label_he' => 'אנשים',
                'multi' => true,
                'ai_enabled' => true,
                'tags' => [
                    ['value' => 'students', 'label_en' => 'Students', 'label_he' => 'תלמידים'],
                    ['value' => 'teachers', 'label_en' => 'Teachers', 'label_he' => 'מורים'],
                    ['value' => 'adults', 'label_en' => 'Adults', 'label_he' => 'מבוגרים'],
                    ['value' => 'children', 'label_en' => 'Children', 'label_he' => 'ילדים'],
                    ['value' => 'group_1', 'label_en' => 'Group of 1', 'label_he' => 'אדם אחד', 'synonyms' => ['1']],
                    ['value' => 'group_2_5', 'label_en' => 'Group of 2–5', 'label_he' => '2–5 אנשים', 'synonyms' => ['2-5', '2–5']],
                    ['value' => 'group_6_20', 'label_en' => 'Group of 6–20', 'label_he' => '6–20 אנשים', 'synonyms' => ['6-20', '6–20']],
                    ['value' => 'group_20_plus', 'label_en' => 'Group of 20+', 'label_he' => '20+ אנשים', 'synonyms' => ['20+']],
                ],
            ],
            [
                'key' => 'setting',
                'label_en' => 'Setting',
                'label_he' => 'סביבה',
                'multi' => true,
                'ai_enabled' => true,
                'tags' => [
                    ['value' => 'indoor', 'label_en' => 'Indoor', 'label_he' => 'בפנים'],
                    ['value' => 'outdoor', 'label_en' => 'Outdoor', 'label_he' => 'בחוץ'],
                    ['value' => 'classroom', 'label_en' => 'Classroom', 'label_he' => 'כיתה'],
                    ['value' => 'auditorium', 'label_en' => 'Auditorium', 'label_he' => 'אולם'],
                    ['value' => 'office', 'label_en' => 'Office', 'label_he' => 'משרד'],
                    ['value' => 'outdoors_nature', 'label_en' => 'Outdoors / nature', 'label_he' => 'טבע'],
                ],
            ],
            [
                'key' => 'activity',
                'label_en' => 'Activity',
                'label_he' => 'פעילות',
                'multi' => true,
                'ai_enabled' => true,
                'tags' => [
                    ['value' => 'lesson', 'label_en' => 'Lesson', 'label_he' => 'שיעור'],
                    ['value' => 'ceremony', 'label_en' => 'Ceremony', 'label_he' => 'טקס'],
                    ['value' => 'workshop', 'label_en' => 'Workshop', 'label_he' => 'סדנה'],
                    ['value' => 'trip', 'label_en' => 'Trip', 'label_he' => 'טיול'],
                    ['value' => 'performance', 'label_en' => 'Performance', 'label_he' => 'הופעה'],
                    ['value' => 'group_photo', 'label_en' => 'Group photo', 'label_he' => 'תמונה קבוצתית'],
                    ['value' => 'meeting', 'label_en' => 'Meeting', 'label_he' => 'פגישה'],
                ],
            ],
            [
                'key' => 'quality',
                'label_en' => 'Quality',
                'label_he' => 'איכות',
                'multi' => true,
                'ai_enabled' => true,
                'tags' => [
                    ['value' => 'sharp', 'label_en' => 'Sharp', 'label_he' => 'חד'],
                    ['value' => 'blurry', 'label_en' => 'Blurry', 'label_he' => 'מטושטש'],
                    ['value' => 'marketing_yes', 'label_en' => 'Usable for marketing', 'label_he' => 'מתאים לשיווק'],
                    ['value' => 'marketing_no', 'label_en' => 'Not for marketing', 'label_he' => 'לא לשיווק'],
                ],
            ],
            [
                'key' => 'program',
                'label_en' => 'Program',
                'label_he' => 'תוכנית',
                'multi' => false,
                'ai_enabled' => false,
                'tags' => [
                    ['value' => 'summer', 'label_en' => 'Summer', 'label_he' => 'קיץ'],
                    ['value' => 'winter', 'label_en' => 'Winter', 'label_he' => 'חורף'],
                    ['value' => 'gap', 'label_en' => 'Gap', 'label_he' => 'גאפ'],
                    ['value' => 'fellowship', 'label_en' => 'Fellowship', 'label_he' => 'מלגה'],
                    ['value' => 'orientation', 'label_en' => 'Orientation', 'label_he' => 'אוריינטציה'],
                    ['value' => 'graduation', 'label_en' => 'Graduation', 'label_he' => 'סיום'],
                ],
            ],
            [
                'key' => 'location',
                'label_en' => 'Location',
                'label_he' => 'מיקום',
                'multi' => false,
                'ai_enabled' => false,
                'tags' => [
                    ['value' => 'nyc', 'label_en' => 'NYC', 'label_he' => 'ניו יורק', 'synonyms' => ['NYC', 'New York']],
                    ['value' => 'sf', 'label_en' => 'SF', 'label_he' => 'סן פרנסיסקו', 'synonyms' => ['SF', 'San Francisco']],
                    ['value' => 'tlv', 'label_en' => 'Tel Aviv', 'label_he' => 'תל אביב', 'synonyms' => ['TLV', 'Tel Aviv']],
                    ['value' => 'jerusalem', 'label_en' => 'Jerusalem', 'label_he' => 'ירושלים'],
                    ['value' => 'boston', 'label_en' => 'Boston', 'label_he' => 'בוסטון'],
                    ['value' => 'chicago', 'label_en' => 'Chicago', 'label_he' => 'שיקגו'],
                    ['value' => 'la', 'label_en' => 'LA', 'label_he' => 'לוס אנג׳לס', 'synonyms' => ['LA', 'Los Angeles']],
                    ['value' => 'london', 'label_en' => 'London', 'label_he' => 'לונדון'],
                ],
            ],
            [
                'key' => 'event',
                'label_en' => 'Event',
                'label_he' => 'אירוע',
                'multi' => true,
                'ai_enabled' => false,
                'tags' => [],
            ],
        ];
    }
}
