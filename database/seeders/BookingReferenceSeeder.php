<?php

namespace Database\Seeders;

use App\Models\Lookup;
use App\Models\Service;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Services and option lists for the public request form. Idempotent and
 * production-safe: adds what's missing, never overwrites admin edits.
 */
class BookingReferenceSeeder extends Seeder
{
    public function run(): void
    {
        $services = [
            ['Stage & Staging', 'panels-top-left', 'Stages, risers, decks and catwalks of any size.'],
            ['Trussing & Rigging', 'route', 'Ground support, flown truss and certified rigging.'],
            ['Barricades', 'shield', 'Front-of-stage and crowd-control barriers.'],
            ['Event Lighting', 'lightbulb', 'Moving lights, washes, followspots and lighting design.'],
            ['LED Screens & Displays', 'monitor-down', 'Indoor and outdoor LED walls and displays.'],
            ['Sound & Audio Production', 'speaker', 'PA systems, monitoring, mixing and engineers.'],
            ['Photography & Videography', 'video', 'Event coverage, multi-camera video and editing.'],
            ['Livestreaming', 'radio', 'Live broadcast to any platform.'],
            ['Full Event Production', 'sparkles', 'End-to-end technical production, planned and run by one team.'],
        ];

        foreach ($services as $i => [$name, $icon, $description]) {
            Service::firstOrCreate(['slug' => Str::slug($name)], [
                'name' => $name, 'icon' => $icon, 'description' => $description,
                'sort_order' => ($i + 1) * 10, 'is_public' => true, 'is_active' => true,
            ]);
        }

        $groups = [
            'event_type' => ['corporate' => 'Corporate Event', 'concert' => 'Concert / Live Show', 'wedding' => 'Wedding', 'conference' => 'Conference / Seminar',
                'church' => 'Church Event', 'awards' => 'Award Ceremony', 'product_launch' => 'Product Launch', 'festival' => 'Festival / Outdoor Event', 'other' => 'Other'],
            'budget_range' => ['under_1m' => 'Under ₦1,000,000', '1m_3m' => '₦1,000,000 – ₦3,000,000', '3m_5m' => '₦3,000,000 – ₦5,000,000',
                '5m_10m' => '₦5,000,000 – ₦10,000,000', '10m_plus' => '₦10,000,000+', 'discuss' => 'Prefer to discuss'],
            'document_category' => ['production_plan' => 'Production plan / technical spec', 'stage_layout' => 'Stage layout', 'technical_drawing' => 'Technical drawing',
                'contract' => 'Contract', 'quotation' => 'Quotation', 'invoice' => 'Invoice', 'equipment_document' => 'Equipment document',
                'inspection_report' => 'Inspection report', 'photo' => 'Photo', 'other' => 'Other'],
        ];

        foreach ($groups as $group => $items) {
            $i = 0;
            foreach ($items as $key => $label) {
                Lookup::firstOrCreate(['group' => $group, 'key' => $key], ['label' => $label, 'sort_order' => (++$i) * 10, 'is_active' => true, 'is_system' => $key === 'other']);
            }
        }
    }
}
