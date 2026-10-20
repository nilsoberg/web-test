<?php

namespace App\Tests\Controller;

use App\Tests\BaseControllerTestCase;
use App\Tests\Factory\UserEntityFactory;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Browser\Test\HasBrowser;

class FeedbackControllerTest extends BaseControllerTestCase
{
    use HasBrowser;

    public function testLegitimateSubmissionSucceeds(): void
    {
        $user = UserEntityFactory::createOne();

        $browser = $this->browser()
            ->actingAs($user)
            ->visit('/feedback')
            ->interceptRedirects();

        $form = $browser->crawler()->selectButton('Send Feedback')->form();
        $form['feedback_form[name]'] = 'John Doe';
        $form['feedback_form[email]'] = 'john@example.com';
        $form['feedback_form[message]'] = 'Hello, this is a legitimate message.';
        $form['feedback_form[website_url]'] = ''; // Honeypot
        // Overwrite the rendered time to simulate a 5 second delay
        $form['feedback_form[form_render_time]'] = (string)(time() - 5);

        $browser->client()->submit($form);

        $browser
            ->assertRedirectedTo('/')
            ->followRedirect()
            ->assertSee('Thank you for your feedback. We will respond shortly.');
    }
    
    public function testBotTrappedWhenHoneypotFilled(): void
    {
        $user = UserEntityFactory::createOne();

        $browser = $this->browser()
            ->actingAs($user)
            ->visit('/feedback')
            ->interceptRedirects();

        $form = $browser->crawler()->selectButton('Send Feedback')->form();

        $form['feedback_form[name]'] = 'Spam Bot';
        $form['feedback_form[email]'] = 'bot@spam.com';
        $form['feedback_form[message]'] = 'pHqghUme attack payload';
        $form['feedback_form[form_render_time]'] = time() - 5;
        $form['feedback_form[website_url]'] = 'http://spam-link.com'; // Honeypot filled!

        $browser->client()->submit($form);

        $browser
            ->assertRedirectedTo('/')
            ->followRedirect()
            ->assertSee('Your message has been sent...');
    }

    public function testBotTrappedWhenSubmittedTooFast(): void
    {
        $user = UserEntityFactory::createOne();

        $browser = $this->browser()
            ->actingAs($user)
            ->visit('/feedback')
            ->interceptRedirects();

        $form = $browser->crawler()->selectButton('Send Feedback')->form();

        $form['feedback_form[name]'] = 'Fast Script';
        $form['feedback_form[email]'] = 'fast@spam.com';
        $form['feedback_form[message]'] = 'Automated payload';
        $form['feedback_form[form_render_time]'] = time(); // 0s delay (< 2s threshold)
        $form['feedback_form[website_url]'] = '';

        $browser->client()->submit($form);

        $browser
            ->assertRedirectedTo('/')
            ->followRedirect()
            ->assertSee('Your message has been sent...');
    }
}
