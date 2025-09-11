<?php

namespace Database\Seeders;

use App\Models\Faq;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class FaqSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $faqs = [
            [
                'question' => 'How do I create an account?',
                'answer' => 'To create an account, click the Sign Up button on the homepage and fill out the registration form.'
            ],
            [
                'question' => 'How can I reset my password?',
                'answer' => 'Click on Forgot Password on the login page and follow the instructions to reset your password.'
            ],
            [
                'question' => 'Can I update my profile information?',
                'answer' => 'Yes, go to your account settings and you can update your name, and other profile details.'
            ],
            [
                'question' => 'How do I contact support?',
                'answer' => 'You can contact our support team through the Contact Us page or by emailing support@example.com.'
            ],
            [
                'question' => 'Is my personal information secure?',
                'answer' => 'Yes, we use industry-standard encryption and follow best practices to ensure your data is secure.'
            ],
        ];

        foreach ($faqs as $faq) {
            Faq::create([
                'question' => $faq['question'],
                'answer' => $faq['answer'],
            ]);
        }

    }
}
