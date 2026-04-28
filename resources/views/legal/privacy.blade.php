<x-marketing.page
    title="Privacy Policy — EBQ"
    description="EBQ's privacy policy. What data we collect, why, how we protect it, and your rights."
>
    <article class="mx-auto max-w-3xl px-6 pb-20 pt-14 lg:px-8 lg:pb-28 lg:pt-20">
        <header>
            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-indigo-200">Legal</p>
            <h1 class="mt-3 text-4xl font-semibold tracking-tight text-white sm:text-5xl">Privacy Policy</h1>
            <p class="mt-3 text-sm text-slate-400">Last updated: {{ \Illuminate\Support\Carbon::create(2026, 4, 28)->format('F j, Y') }}</p>
            <p class="mt-6 text-base leading-7 text-slate-200">
                This Privacy Policy explains what personal data EBQ ("we", "us", "our") collects, why we collect it, how we protect it, and the rights you have over your data when you use the EBQ.io platform, the EBQ WordPress plugin, and any associated services (the "Service").
            </p>
        </header>

        <div class="prose prose-invert mt-10 max-w-none text-slate-200 prose-headings:text-white prose-h2:mt-12 prose-h2:text-2xl prose-h3:mt-8 prose-h3:text-lg prose-a:text-indigo-200 hover:prose-a:text-indigo-100 prose-strong:text-white">

            <h2>1. Data we collect</h2>

            <h3>1.1 Account data</h3>
            <p>When you sign up we collect your name, email, password (hashed), and the workspace / company details you provide. If you sign in with Google we receive your basic profile (name, email, profile picture) from Google.</p>

            <h3>1.2 Connected-service data</h3>
            <p>To compute SEO scores and reports we connect, with your consent, to:</p>
            <ul>
                <li><strong>Google Search Console</strong> — query, page, clicks, impressions, position, country, device.</li>
                <li><strong>Google Analytics</strong> — pageviews, sessions, sources (when you connect it).</li>
                <li><strong>Google Indexing API</strong> — URL submission requests you initiate.</li>
                <li><strong>Keywords Everywhere</strong> — keyword volume / CPC / backlink data for queries you research.</li>
                <li><strong>Serper</strong> — live SERP results for keywords you enter.</li>
            </ul>
            <p>The data flows from those providers to our servers and stays in your workspace. We do not pool it across customers.</p>

            <h3>1.3 Content data</h3>
            <p>The WordPress plugin sends post content, titles, meta descriptions, focus keyphrases, and similar editor fields to EBQ when you use scoring, brief, AI Writer, or schema features. We process this data to return the requested analysis and store cached results so re-runs are fast.</p>

            <h3>1.4 Usage data</h3>
            <p>We log standard server-side telemetry — IP address, user-agent, request paths, HTTP status, latency — for operational reasons (debugging, abuse prevention, capacity planning). We aggregate this for product analytics; we do not build behavioural profiles.</p>

            <h3>1.5 Billing data</h3>
            <p>Payments are processed by our payment processor (e.g. Stripe). We receive only the last 4 digits of your card, brand, expiry, billing address, and the resulting charge / invoice metadata. We do not store full card numbers.</p>

            <h2>2. How we use the data</h2>
            <ul>
                <li><strong>To provide the Service</strong> — render dashboards, generate audits, score posts, deliver AI output, sync GSC data, etc.</li>
                <li><strong>To operate and secure the Service</strong> — authenticate, prevent abuse, debug incidents, maintain uptime.</li>
                <li><strong>To bill</strong> — manage subscriptions, send invoices, collect payment.</li>
                <li><strong>To communicate</strong> — service announcements, security alerts, billing receipts. Marketing email only with your opt-in, with a one-click unsubscribe in every message.</li>
                <li><strong>To improve the product</strong> — aggregate, anonymised metrics on feature use. We do not train AI models on Customer Content.</li>
            </ul>

            <h2>3. Legal bases (GDPR)</h2>
            <p>Where the GDPR applies, we process personal data under the following bases:</p>
            <ul>
                <li><strong>Contract performance</strong> — to deliver the Service you asked for.</li>
                <li><strong>Legitimate interests</strong> — security, fraud prevention, product analytics. We balance these against your rights.</li>
                <li><strong>Consent</strong> — for connecting third-party data sources (Google, Keywords Everywhere) and for marketing email. You can withdraw consent at any time.</li>
                <li><strong>Legal obligation</strong> — tax, accounting, compliance with regulator requests.</li>
            </ul>

            <h2>4. Sharing</h2>
            <p>We share data only with:</p>
            <ul>
                <li><strong>Sub-processors</strong> we rely on to operate the Service (cloud hosting, payment processing, transactional email, error monitoring, AI inference). Each is bound by a Data Processing Agreement.</li>
                <li><strong>Authorities</strong> when legally required (subpoena, court order). We will challenge over-broad requests where possible.</li>
                <li><strong>Successors</strong> in case of a merger, acquisition, or asset sale — and only on terms at least as protective as this policy.</li>
            </ul>
            <p>We do not sell your personal data. We do not share Customer Content (post drafts, audits, GSC rows, AI outputs) with anyone outside your workspace.</p>

            <h3>4.1 Sub-processors we use</h3>
            <ul>
                <li>Cloud hosting and database (e.g. AWS / DigitalOcean)</li>
                <li>Payment processing (e.g. Stripe)</li>
                <li>Transactional email (e.g. Postmark / Resend / AWS SES)</li>
                <li>Error and performance monitoring (e.g. Sentry)</li>
                <li>AI inference (e.g. Mistral)</li>
                <li>SERP data (Serper)</li>
                <li>Keyword data (Keywords Everywhere)</li>
            </ul>
            <p>An up-to-date sub-processor list is available on request.</p>

            <h2>5. International transfers</h2>
            <p>EBQ stores data in cloud regions chosen for performance and cost. When data moves outside your region we rely on Standard Contractual Clauses (or an equivalent legal mechanism) with our hosting and sub-processors. Email <a href="mailto:privacy@ebq.io">privacy@ebq.io</a> for current region details.</p>

            <h2>6. Retention</h2>
            <ul>
                <li><strong>Active accounts</strong> — retained while your subscription is active.</li>
                <li><strong>Cancelled accounts</strong> — Customer Content is retained for 30 days after cancellation, then deleted (excluding billing records we're legally required to keep).</li>
                <li><strong>GSC / Analytics syncs</strong> — historical rows are kept for the rolling window your plan supports (typically 16 months, matching Google's own retention).</li>
                <li><strong>Server logs</strong> — 30 days, then aggregated.</li>
                <li><strong>Billing records</strong> — 7 years (or longer where required by tax law).</li>
            </ul>

            <h2>7. Your rights</h2>
            <p>Depending on where you live, you may have the right to:</p>
            <ul>
                <li>Access the personal data we hold about you.</li>
                <li>Correct inaccurate data.</li>
                <li>Delete your data ("right to be forgotten").</li>
                <li>Restrict or object to certain processing.</li>
                <li>Receive a portable copy of your data.</li>
                <li>Withdraw consent (without affecting prior lawful processing).</li>
                <li>Lodge a complaint with your local data-protection authority.</li>
            </ul>
            <p>To exercise any of these rights, email <a href="mailto:privacy@ebq.io">privacy@ebq.io</a>. We respond within 30 days.</p>

            <h2>8. Security</h2>
            <p>We protect data with TLS in transit, at-rest encryption on managed databases, scoped per-website API tokens, role-based access control, and audit logging. Infrastructure access is restricted to a small number of personnel with 2FA. We run regular dependency updates and security reviews.</p>
            <p>No system is perfectly secure. If we discover a breach affecting your data, we will notify you and the appropriate authorities without undue delay, in line with applicable law.</p>

            <h2>9. Cookies</h2>
            <p>EBQ.io uses essential cookies for sign-in sessions and CSRF protection, plus a small number of first-party analytics cookies for product usage metrics. We do not use third-party advertising cookies. You can clear cookies in your browser at any time; doing so will sign you out.</p>

            <h2>10. Children</h2>
            <p>The Service is not directed to anyone under 16. We do not knowingly collect personal data from children under 16. If you believe we have, contact <a href="mailto:privacy@ebq.io">privacy@ebq.io</a> and we will delete it.</p>

            <h2>11. Changes to this policy</h2>
            <p>We will post any changes on this page and, for material changes, notify account owners by email at least 14 days before they take effect.</p>

            <h2>12. Contact</h2>
            <p>For privacy questions or requests, email <a href="mailto:privacy@ebq.io">privacy@ebq.io</a>. We'll route the request to the right person and respond as quickly as we can.</p>
        </div>
    </article>
</x-marketing.page>
