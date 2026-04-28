<x-marketing.page
    title="Privacy Policy — EBQ"
    description="How EBQ collects, uses, and protects personal data."
>
    <article class="bg-white">
        <header class="border-b border-slate-200">
            <div class="mx-auto max-w-3xl px-6 py-16 lg:px-8 lg:py-20">
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Legal</p>
                <h1 class="mt-3 text-balance text-4xl font-semibold tracking-tight text-slate-900 sm:text-5xl">Privacy Policy</h1>
                <p class="mt-3 text-sm text-slate-500">Last updated: {{ \Illuminate\Support\Carbon::create(2026, 4, 28)->format('F j, Y') }}</p>
                <p class="mt-6 text-[16px] leading-7 text-slate-600">
                    This policy explains what information EBQ collects, why we process it, and how you can exercise your privacy rights.
                </p>
            </div>
        </header>

        <div class="mx-auto max-w-3xl px-6 py-16 lg:px-8 lg:py-20">
            <div class="prose prose-slate max-w-none prose-headings:tracking-tight prose-h2:mt-12 prose-h2:text-xl prose-h2:font-semibold prose-h2:text-slate-900 prose-p:text-slate-600 prose-li:text-slate-600 prose-a:text-slate-900 prose-a:underline-offset-2">
                <h2>1. Data we collect</h2>
                <ul>
                    <li>Account data: name, email, authentication details, workspace metadata.</li>
                    <li>Connected data: Google Search Console, Google Analytics, and Indexing API data you authorize.</li>
                    <li>Operational telemetry: IP, request logs, and performance diagnostics.</li>
                    <li>Billing metadata processed by your payment provider.</li>
                </ul>

                <h2>2. How we use data</h2>
                <ul>
                    <li>Deliver product features such as dashboards, insights, and reporting.</li>
                    <li>Secure and operate the service.</li>
                    <li>Send service communications and billing notifications.</li>
                    <li>Improve user-facing product quality through aggregated analysis.</li>
                </ul>

                <h2>3. Google API Services User Data Policy compliance</h2>
                <p>
                    EBQ's use of Google data follows the <a href="https://developers.google.com/terms/api-services-user-data-policy" rel="noopener noreferrer">Google API Services User Data Policy</a>, including Limited Use requirements.
                </p>
                <ul>
                    <li>We use Google data only to provide user-visible EBQ features.</li>
                    <li>We do not sell Google data or use it for advertising profiling.</li>
                    <li>We do not use Google data to train general-purpose AI models.</li>
                    <li>We do not transfer Google user data to data brokers or information resellers.</li>
                    <li>We only request scopes required for product functionality and honor scope revocation.</li>
                </ul>

                <h2>4. Sharing and sub-processors</h2>
                <p>We share data only with sub-processors needed to run the service (hosting, email, payments, monitoring) or when required by law. Sub-processors process data on our instructions and under confidentiality obligations.</p>

                <h2>5. Security controls</h2>
                <p>We use industry-standard safeguards including encryption in transit, encrypted storage for sensitive tokens, access controls, audit logging, and least-privilege operational practices.</p>

                <h2>6. Retention and deletion</h2>
                <p>We retain account and connected data while your account is active. After closure, we delete or anonymize data subject to legal, fraud-prevention, and operational retention requirements.</p>
                <p>You can remove Google access at any time in your Google Account permissions and can request account/data deletion by contacting us.</p>

                <h2>7. Your rights</h2>
                <p>You can request access, correction, deletion, restriction, or portability by contacting <a href="mailto:privacy@ebq.io">privacy@ebq.io</a>.</p>

                <h2>8. Changes to this policy</h2>
                <p>We may update this Privacy Policy to reflect legal, product, or security changes. Material changes will be posted on this page with an updated effective date.</p>

                <h2>9. Contact</h2>
                <p>Privacy requests: <a href="mailto:privacy@ebq.io">privacy@ebq.io</a>.</p>
            </div>
        </div>
    </article>
</x-marketing.page>
