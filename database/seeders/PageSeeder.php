<?php

namespace Database\Seeders;

use App\Models\Page;
use Illuminate\Database\Seeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class PageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $pages = [
            [
                'type' => 'Terms & Conditions',
                'text' => '
              <h1>Terms and Conditions</h1>
                <p>By accessing or using our platform, you agree to be bound by the following terms and conditions. Please read them carefully.</p>

                <h2>1. Acceptance of Terms</h2>
                <p>By registering or using any part of our services, you accept these terms and agree to comply with them.</p>

                <h2>2. User Responsibilities</h2>
                <p>You agree to use the platform lawfully, not to violate any applicable laws, and to ensure that your use does not harm others or the platform.</p>

                <h2>3. Account Security</h2>
                <p>You are responsible for maintaining the confidentiality of your login credentials. Notify us immediately if you suspect unauthorized access.</p>

                <h2>4. Termination</h2>
                <p>We reserve the right to suspend or terminate your account if you violate these terms.</p>

                <h2>5. Modifications</h2>
                <p>We may update these terms at any time. Continued use of the platform after changes means you accept the updated terms.</p>

                <p>For questions or concerns, please <a href="contact-us">contact us</a>.</p>
            '
            ],
            [
                'type' => 'Privacy Policy',
                'text' => '
                <h1>Privacy Policy</h1>
                <p>We respect your privacy and are committed to protecting your personal information. This policy outlines how we collect, use, and store your data.</p>

                <h2>1. Information We Collect</h2>
                <p>We may collect personal details like your name, email address, and browsing behavior.</p>

                <h2>2. How We Use Your Information</h2>
                <p>To provide services, send updates, and improve user experience.</p>

                <h2>3. Sharing of Information</h2>
                <p>We do not sell your information. We may share with partners under strict privacy terms.</p>

                <h2>4. Cookies</h2>
                <p>We use cookies to enhance your browsing experience.</p>

                <p>Contact us for any privacy-related queries.</p>
            '
            ],
            [
                'type' => 'About Us',
                'text' => '
                <h1>About Us</h1>
                <p>We are a passionate team committed to delivering the best service and support to our users.</p>

                <p>Our mission is to make technology accessible, simple, and valuable for everyone.</p>
            '
            ]
        ];

        foreach ($pages as $page) {
            Page::create([
                'type' => $page['type'],
                'text' => $page['text'],
            ]);
        }

    }
}
