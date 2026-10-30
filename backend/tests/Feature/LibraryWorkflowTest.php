<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\BookCopy;
use App\Models\Borrowing;
use App\Models\Fine;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LibraryWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_librarian_can_check_out_an_available_copy_to_an_active_member(): void
    {
        $librarian = User::factory()->create(['role' => 'librarian', 'status' => 'active']);
        $member = User::factory()->create(['role' => 'member', 'status' => 'active']);
        $copy = $this->copy();

        $response = $this->actingAs($librarian)->postJson('/api/borrowings', [
            'book_copy_id' => $copy->id,
            'user_id' => $member->id,
        ]);

        $response->assertCreated()->assertJsonPath('status', 'borrowed');
        $this->assertSame('borrowed', $copy->fresh()->status);
        $this->assertDatabaseHas('borrowings', ['book_copy_id' => $copy->id, 'user_id' => $member->id, 'status' => 'borrowed']);
    }

    public function test_overdue_return_creates_a_fine_and_releases_the_copy(): void
    {
        $librarian = User::factory()->create(['role' => 'librarian', 'status' => 'active']);
        $member = User::factory()->create(['role' => 'member', 'status' => 'active']);
        $copy = $this->copy('borrowed');
        $loan = Borrowing::create([
            'book_copy_id' => $copy->id,
            'user_id' => $member->id,
            'issued_by' => $librarian->id,
            'borrowed_at' => now()->subDays(20),
            'due_at' => now()->subDays(2),
            'status' => 'borrowed',
        ]);

        $this->actingAs($librarian)->postJson('/api/borrowings/'.$loan->id.'/return')
            ->assertOk()
            ->assertJsonPath('fine.status', 'unpaid');

        $this->assertSame('returned', $loan->fresh()->status);
        $this->assertSame('available', $copy->fresh()->status);
        $this->assertEquals(1.00, (float) Fine::where('borrowing_id', $loan->id)->value('amount'));
    }

    public function test_return_allocates_the_copy_to_the_oldest_waiting_reservation(): void
    {
        $librarian = User::factory()->create(['role' => 'librarian', 'status' => 'active']);
        $member = User::factory()->create(['role' => 'member', 'status' => 'active']);
        $copy = $this->copy('borrowed');
        $loan = Borrowing::create([
            'book_copy_id' => $copy->id,
            'user_id' => User::factory()->create(['role' => 'member'])->id,
            'issued_by' => $librarian->id,
            'borrowed_at' => now()->subDays(3),
            'due_at' => now()->addDays(11),
            'status' => 'borrowed',
        ]);
        $this->actingAs($member)->postJson('/api/books/'.$copy->book_id.'/reservations')->assertCreated();

        $this->actingAs($librarian)->postJson('/api/borrowings/'.$loan->id.'/return')->assertOk();

        $this->assertDatabaseHas('reservations', ['user_id' => $member->id, 'book_id' => $copy->book_id, 'status' => 'ready', 'book_copy_id' => $copy->id]);
        $this->assertSame('reserved', $copy->fresh()->status);
    }

    public function test_members_only_see_their_own_loans_and_cannot_manage_member_accounts(): void
    {
        $member = User::factory()->create(['role' => 'member', 'status' => 'active']);
        $otherMember = User::factory()->create(['role' => 'member', 'status' => 'active']);
        $librarian = User::factory()->create(['role' => 'librarian', 'status' => 'active']);
        $first = $this->copy('borrowed');
        $second = $this->copy('borrowed');
        foreach ([[$first, $member], [$second, $otherMember]] as [$copy, $owner]) {
            Borrowing::create([
                'book_copy_id' => $copy->id,
                'user_id' => $owner->id,
                'issued_by' => $librarian->id,
                'borrowed_at' => now(),
                'due_at' => now()->addDays(14),
                'status' => 'borrowed',
            ]);
        }

        $this->actingAs($member)->getJson('/api/borrowings')->assertOk()->assertJsonCount(1, 'data');
        $this->actingAs($member)->getJson('/api/members')->assertForbidden();
        $this->actingAs($member)->postJson('/api/books', [])->assertForbidden();
    }

    private function copy(string $status = 'available'): BookCopy
    {
        $book = Book::create(['title' => 'A Practical Library', 'language' => 'en']);
        return BookCopy::create([
            'book_id' => $book->id,
            'accession_number' => 'ACC-'.strtoupper(bin2hex(random_bytes(4))),
            'status' => $status,
        ]);
    }
}
