<?php

namespace Tests\Feature;

use App\Models\DebitCard;
use App\Models\DebitCardTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\Fluent\AssertableJson;
use Laravel\Passport\Passport;
use Tests\TestCase;
use Symfony\Component\HttpFoundation\Response;


class DebitCardControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        Passport::actingAs($this->user);
    }

    public function testCustomerCanSeeAListOfDebitCards()
    {
        $quantity = rand(0,10);
        DebitCard::factory()->count($quantity)->active()->create([
                'user_id' => $this->user->id,
        ]);

        $this->get('api/debit-cards')
            ->assertStatus(Response::HTTP_OK)
            ->assertJsonCount($quantity);
    }

    public function testCustomerCannotSeeAListOfDebitCardsOfOtherCustomers()
    {
        $newUser = User::factory()->create();
        DebitCard::factory()->count(10)->active()->create([
            'user_id' => $newUser->id,
        ]);

        $this->get('api/debit-cards')
            ->assertStatus(Response::HTTP_OK)
            ->assertJsonCount(0);
    }

    public function testCustomerCanCreateADebitCard()
    {
        $response = $this->post('api/debit-cards', ['type' => 'Visa']);
        $response->assertStatus(Response::HTTP_CREATED)
            ->assertJsonStructure([
                'id',
                'number',
                'type',
                'expiration_date',
                'is_active'
            ]);
    }

    public function testCustomerCanSeeASingleDebitCardDetails()
    {
        $debitCard = DebitCard::factory()->active()->create([
            'user_id' => $this->user->id,
        ]);


        $response = $this->get('/api/debit-cards/' . $debitCard->id);

        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonStructure([
                'id',
                'number',
                'type',
                'expiration_date',
                'is_active'
            ]);
    }

    /**
     * @todo check later
     */
    public function testCustomerCannotSeeASingleDebitCardDetails()
    {
        $newUser = User::factory()->create();

        $debitCard = DebitCard::factory()->create(['user_id' => $newUser->id]);

        $response = $this->get('/api/debit-cards/' . $debitCard->id);
        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    public function testCustomerCanActivateADebitCard()
    {
        $debitCard = DebitCard::factory()->create([
            'user_id' => $this->user->id,
            'disabled_at' => null,
        ]);
        $response = $this->put("api/debit-cards/{$debitCard->id}", ['is_active' => true]);
        $response->assertOk();
        $reLoadDebitCard = DebitCard::where('id', $debitCard->id)->first();

        $this->assertEquals(true, $reLoadDebitCard->getIsActiveAttribute());
    }

    public function testCustomerCanDeactivateADebitCard()
    {

        $debitCard = DebitCard::factory()->active()->create([
            'user_id' => $this->user->id,
        ]);
        $response = $this->put("api/debit-cards/{$debitCard->id}", ['is_active' => false]);
        $response->assertOk();
        $reLoadDebitCard = DebitCard::where('id', $debitCard->id)->first();

        $this->assertEquals(false, $reLoadDebitCard->getIsActiveAttribute());
    }

    public function testCustomerCannotUpdateADebitCardWithWrongValidation()
    {

        $invalidValue = "asdsadsad";

        $debitCard = DebitCard::factory()->create([
            'user_id' => $this->user->id,
        ]);
        $response = $this->put("api/debit-cards/".$debitCard->id, ['is_active' => $invalidValue]);
        $response->assertStatus(Response::HTTP_FOUND);

        $reLoadDebitCard = DebitCard::where('id', $debitCard->id)->first();
        $this->assertNotEquals($invalidValue, $reLoadDebitCard->disabled_at);
        $this->assertNull($reLoadDebitCard->disabled_at);
    }

    public function testCustomerCanDeleteADebitCard()
    {
        $debitCard = DebitCard::factory()->create([
            'user_id' => $this->user->id,
        ]);
        $response = $this->delete('api/debit-cards/' . $debitCard->id);
        $response->assertStatus(Response::HTTP_NO_CONTENT);
        $reLoadDebitCard = DebitCard::where('id', $debitCard->id)->first();
        $this->assertNull($reLoadDebitCard);

    }

    public function testCustomerCannotDeleteADebitCardWithTransaction()
    {

        $debitCard = DebitCard::factory()->create([
            'user_id' => $this->user->id,
        ]);

        $transaction = DebitCardTransaction::factory()->create([
            'debit_card_id' => $debitCard->id
        ]);

        $response = $this->delete("api/debit-cards/{$debitCard->id}");
        $response->assertForbidden();
        $reLoadDebitCard = DebitCard::where('id', $debitCard->id)->first();
        $this->assertNotNull($reLoadDebitCard);

    }

    // Extra bonus for extra tests :)
}
