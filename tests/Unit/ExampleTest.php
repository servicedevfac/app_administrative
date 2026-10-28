<?php

namespace Tests\Unit;

use App\Models\Article;
use App\Models\User;
use PHPUnit\Framework\TestCase;

class ExampleTest extends TestCase
{
    public function test_stock_state_is_calculated_in_one_place(): void
    {
        $this->assertSame([
            'quantity' => 0,
            'status' => Article::STATUS_OUT,
            'is_available' => false,
        ], Article::stockState(0, 5));

        $this->assertSame(Article::STATUS_LOW, Article::stockState(5, 5)['status']);
        $this->assertSame(Article::STATUS_AVAILABLE, Article::stockState(6, 5)['status']);
    }

    public function test_user_roles_are_normalized_in_one_place(): void
    {
        $this->assertSame(User::ROLE_ADMIN, User::normalizeRole('Administrateur'));
        $this->assertSame(User::ROLE_ADMIN, User::normalizeRole('manager'));
        $this->assertSame(User::ROLE_COLLABORATOR, User::normalizeRole('Collaborateur'));
        $this->assertSame(User::ROLE_COLLABORATOR, User::normalizeRole(null));
    }
}
