<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Author;
use App\Models\Book;
use App\Models\BookCopy;
use App\Models\Category;
use App\Models\LibrarySetting;
use App\Models\Publisher;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $admin = User::updateOrCreate(['email' => 'admin@library.test'], [
            'name' => 'Morgan Ellis',
            'password' => 'password',
            'role' => 'admin',
            'status' => 'active',
            'membership_code' => 'STAFF-0001',
        ]);
        User::updateOrCreate(['email' => 'librarian@library.test'], [
            'name' => 'Jordan Lee',
            'password' => 'password',
            'role' => 'librarian',
            'status' => 'active',
            'membership_code' => 'STAFF-0002',
        ]);
        User::updateOrCreate(['email' => 'member@library.test'], [
            'name' => 'Taylor Morgan',
            'password' => 'password',
            'role' => 'member',
            'status' => 'active',
            'membership_code' => 'M-10001',
        ]);

        $publisher = Publisher::firstOrCreate(['name' => 'Northstar Press'], ['website' => 'https://example.com']);
        $authors = collect([
            'Maya Chen',
            'Samuel Okafor',
            'Elena Ruiz',
            'Avery Patel',
        ])->mapWithKeys(fn ($name) => [$name => Author::firstOrCreate(['name' => $name])]);
        $categories = collect([
            'Technology',
            'History',
            'Literature',
            'Science',
        ])->mapWithKeys(fn ($name) => [$name => Category::firstOrCreate(['name' => $name])]);

        $titles = [
            ['isbn' => '9780000000001', 'title' => 'The Thoughtful Engineer', 'author' => 'Maya Chen', 'category' => 'Technology', 'year' => 2023],
            ['isbn' => '9780000000002', 'title' => 'A Map of Small Stars', 'author' => 'Elena Ruiz', 'category' => 'Literature', 'year' => 2021],
            ['isbn' => '9780000000003', 'title' => 'Cities Through Time', 'author' => 'Samuel Okafor', 'category' => 'History', 'year' => 2020],
            ['isbn' => '9780000000004', 'title' => 'The Living Ocean', 'author' => 'Avery Patel', 'category' => 'Science', 'year' => 2024],
        ];

        foreach ($titles as $index => $data) {
            $book = Book::firstOrCreate(['isbn' => $data['isbn']], [
                'title' => $data['title'],
                'publisher_id' => $publisher->id,
                'published_year' => $data['year'],
                'language' => 'en',
                'created_by' => $admin->id,
            ]);
            $book->authors()->syncWithoutDetaching([$authors[$data['author']]->id]);
            $book->categories()->syncWithoutDetaching([$categories[$data['category']]->id]);
            for ($copy = 1; $copy <= ($index === 0 ? 3 : 2); $copy++) {
                BookCopy::firstOrCreate(
                    ['accession_number' => sprintf('ACC-%04d-%02d', $index + 1, $copy)],
                    ['book_id' => $book->id, 'status' => 'available', 'shelf_location' => 'A'.($index + 1).'-'.$copy],
                );
            }
        }

        foreach ([
            'library_name' => 'LNU SmartLib',
            'loan_days' => 14,
            'fine_per_day' => 0.5,
            'reservation_hold_hours' => 48,
            'max_active_loans' => 5,
        ] as $key => $value) {
            LibrarySetting::firstOrCreate(['key' => $key], ['value' => ['value' => $value]]);
        }
    }
}
