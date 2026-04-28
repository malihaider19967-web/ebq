<x-marketing.page
    title="Terms of Service — EBQ"
    description="EBQ Terms of Service. The agreement that governs your use of EBQ's SEO platform and WordPress plugin."
>
    <article class="mx-auto max-w-3xl px-6 pb-20 pt-14 lg:px-8 lg:pb-28 lg:pt-20">
        <header>
            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-indigo-200">Legal</p>
            <h1 class="mt-3 text-4xl font-semibold tracking-tight text-white sm:text-5xl">Terms of Service</h1>
            <p class="mt-3 text-sm text-slate-400">Last updated: {{ \Illuminate\Support\Carbon::create(2026, 4, 28)->format('F j, Y') }}</p>
            <p class="mt-6 text-base leading-7 text-slate-200">
                Welcome to EBQ. These Terms of Service ("Terms") govern your access to and use of the EBQ.io platform, the EBQ WordPress plugin, and any associated services (together, the "Service") operated by EBQ ("we", "us", "our"). By creating an account or using the Service, you agree to these Terms.
            </p>
        </header>

        <div class="prose prose-invert mt-10 max-w-none text-slate-200 prose-headings:text-white prose-h2:mt-12 prose-h2:text-2xl prose-h3:mt-8 prose-h3:text-lg prose-a:text-indigo-200 hover:prose-a:text-indigo-100 prose-strong:text-white">

            <h2>1. Accounts</h2>
            <p>You must be at least 18 years old (or the legal age of majority where you reside) to create an EBQ account. You're responsible for keeping your login credentials confidential, for all activity that happens under your account, and for promptly notifying us if you suspect unauthorized access.</p>
            <p>You agree to provide accurate information when registering and to keep that information up to date. We may suspend or terminate accounts that contain false information or that are used in breach of these Terms.</p>

            <h2>2. The Service</h2>
            <p>EBQ provides SEO analytics, content tooling, audit pipelines, and reporting features for websites you control. The Service is delivered through:</p>
            <ul>
                <li><strong>EBQ.io</strong> — the workspace web application.</li>
                <li><strong>EBQ WordPress plugin</strong> — the editor sidebar and HQ admin pages installed on your WordPress site.</li>
                <li><strong>EBQ APIs</strong> — REST endpoints used by the plugin and any integrations you authorise.</li>
            </ul>
            <p>We may add, modify, or remove features over time. When changes meaningfully reduce functionality on a paid plan, we will give reasonable advance notice and offer a pro-rated refund or migration path.</p>

            <h3>2.1 Third-party data sources</h3>
            <p>EBQ pulls data from Google Search Console, Google Analytics, Serper, Mistral AI, Keywords Everywhere, and other providers you connect. Your use of those data sources is governed by their own terms. EBQ is not responsible for outages, rate limits, or data discrepancies originating with third parties.</p>

            <h2>3. Acceptable use</h2>
            <p>You agree not to:</p>
            <ul>
                <li>Use the Service to scrape, attack, or otherwise interfere with sites you do not own or have permission to operate on.</li>
                <li>Submit content that is unlawful, infringes another party's rights, or contains malware.</li>
                <li>Attempt to bypass rate limits, plan quotas, or authentication mechanisms.</li>
                <li>Resell, sublicense, or white-label the Service except as expressly permitted by an Agency plan.</li>
                <li>Use the Service to generate AI content that violates the policies of the underlying model provider (for example, generating content prohibited by Mistral's usage policies).</li>
            </ul>

            <h2>4. Subscriptions and payment</h2>
            <p>Paid plans are billed in advance on a monthly or annual cycle. The price displayed at checkout is what you pay (excluding sales tax / VAT where applicable). You authorise us, or our payment processor, to charge your selected payment method on each renewal.</p>
            <p>Annual plans receive a discount equivalent to two months free.</p>
            <p>If a payment fails, we will retry the charge and notify you. If payment cannot be collected within 14 days, we may suspend or downgrade the account until the balance is settled.</p>

            <h3>4.1 Free trial</h3>
            <p>Paid plans include a 14-day free trial. You can cancel anytime during the trial without being charged. If you do not cancel before the trial ends, the plan auto-converts and your card is billed for the first cycle.</p>

            <h3>4.2 Refunds</h3>
            <p>Refunds are governed by our <a href="{{ route('refund-policy') }}">Refund Policy</a>, which forms part of these Terms.</p>

            <h2>5. Your content and data</h2>
            <p>You retain ownership of all content you upload, generate, or connect through the Service ("Customer Content"), including post drafts, audit results, keyword lists, and any data EBQ stores on your behalf from connected providers (Search Console rows, Analytics metrics, etc).</p>
            <p>You grant us a worldwide, non-exclusive, royalty-free licence to host, copy, transmit, display, and process Customer Content solely to operate, secure, and improve the Service for you. We do not sell Customer Content. We do not use Customer Content to train AI models. We do not share Customer Content with other customers.</p>

            <h2>6. AI-generated content</h2>
            <p>The AI Writer and related features generate content using third-party large-language models. You are responsible for reviewing AI output before publishing, including for accuracy, originality, and compliance with applicable laws (e.g. medical, legal, financial advice). EBQ makes no warranty that AI output is correct, original, or fit for purpose.</p>

            <h2>7. Intellectual property</h2>
            <p>The Service — including software, design, branding, and documentation — is owned by EBQ and protected by copyright, trademark, and other laws. We grant you a limited, revocable, non-transferable licence to use the Service in accordance with these Terms. You may not reverse-engineer the Service or extract its source code except where prohibition is barred by applicable law.</p>
            <p>The EBQ WordPress plugin is licensed under GPL v2 or later. The same code may be redistributed under that licence; the hosted Service it connects to is proprietary.</p>

            <h2>8. Termination</h2>
            <p>You may cancel your subscription anytime from the billing settings. Cancellation stops future renewals. Your data remains accessible for the remainder of the paid period and is then archived for 30 days before deletion.</p>
            <p>We may suspend or terminate your account immediately if you breach these Terms, fail to pay, or use the Service in a way that risks our infrastructure or another customer's data. Where we terminate without cause, we will refund any unused prepaid time on a pro-rata basis.</p>

            <h2>9. Disclaimers</h2>
            <p>The Service is provided "as is" without warranties of any kind, express or implied, including warranties of merchantability, fitness for a particular purpose, or non-infringement. We do not guarantee that the Service will be uninterrupted, error-free, or that any specific SEO outcome (rankings, traffic, conversions) will result from its use.</p>

            <h2>10. Limitation of liability</h2>
            <p>To the maximum extent permitted by law, EBQ's total aggregate liability for any claim arising out of or relating to the Service is limited to the amount you paid us in the 12 months preceding the event giving rise to the claim. EBQ is not liable for indirect, incidental, special, consequential, or punitive damages, or for lost profits, lost revenue, or lost data.</p>

            <h2>11. Indemnification</h2>
            <p>You agree to indemnify and hold EBQ, its affiliates, and personnel harmless from any third-party claim arising out of (a) your use of the Service in breach of these Terms, (b) Customer Content, or (c) your violation of any law or third-party right.</p>

            <h2>12. Changes to the Terms</h2>
            <p>We may update these Terms from time to time. When we make material changes we will notify account owners by email at least 14 days before the change takes effect. Continued use of the Service after the effective date constitutes acceptance of the updated Terms.</p>

            <h2>13. Governing law and disputes</h2>
            <p>These Terms are governed by the laws of the jurisdiction where EBQ is incorporated, without regard to its conflict-of-laws provisions. Any dispute arising out of or relating to these Terms shall be resolved by binding arbitration or in the competent courts of that jurisdiction, at EBQ's option, except where you have a non-waivable right to bring claims in your local courts.</p>

            <h2>14. Contact</h2>
            <p>Questions about these Terms? Email <a href="mailto:legal@ebq.io">legal@ebq.io</a>.</p>
        </div>
    </article>
</x-marketing.page>
