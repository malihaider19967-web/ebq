<?php

namespace App\Support\Audit;

/**
 * Merged search-intent substring triggers: English baseline ∪ every shipped language pack (memoized).
 */
class IntentTriggerVocabulary
{
    /** @var array<string, list<string>>|null */
    private static ?array $mergedSortedCache = null;

    /**
     * @return list<string> longest-first unique triggers for the bucket
     */
    public static function mergedSorted(string $bucket): array
    {
        if (self::$mergedSortedCache === null) {
            self::$mergedSortedCache = self::buildAllBuckets();
        }

        return self::$mergedSortedCache[$bucket] ?? [];
    }

    /**
     * @return array<string, list<string>>
     */
    private static function buildAllBuckets(): array
    {
        $bucketNames = [
            'commercial', 'transactional', 'navigational', 'local', 'support', 'utility', 'informational',
        ];
        $base = self::englishPacks();
        $extra = self::additionalLanguagePacks();
        $out = [];
        foreach ($bucketNames as $bucket) {
            $merged = $base[$bucket] ?? [];
            foreach ($extra as $pack) {
                if (isset($pack[$bucket]) && is_array($pack[$bucket])) {
                    $merged = array_merge($merged, $pack[$bucket]);
                }
            }
            $out[$bucket] = self::sortTriggersDesc($merged);
        }

        return $out;
    }

    /**
     * @return array<string, list<string>>
     */
    private static function englishPacks(): array
    {
        return [
            'commercial' => [
                'difference between', 'which is better', 'pros and cons', 'worth the money', 'scam or legit',
                'alternative to', 'best overall', 'top-rated', 'top rated', 'specifications', 'best alternative',
                'comparison', 'competitors', 'alternatives', 'benchmark', 'benefits', 'is it good', 'is it worth',
                'versus', 'reviews', 'review', 'rating', 'specs', 'best', 'vs',
            ],
            'transactional' => [
                'promo code', 'promocode', 'premium version', 'upgrade to pro', 'get it now', 'free trial', 'freetrial',
                'for sale', 'subscription', 'affordable', 'clearance', 'checkout',
                'purchase', 'discount', 'voucher', 'coupon', 'reserve', 'pricing', 'enroll', 'cheap', 'deal',
                'order', 'shop', 'hire', 'book', 'cost', 'price', 'buy',
            ],
            'navigational' => [
                'customer service', 'support phone', 'official site', 'my account', 'home page', 'homepage', 'dashboard', 'sign-in',
                'sign in', 'signin', 'log in', 'login', 'portal', 'account', 'contact', 'careers', 'career',
                'jobs', 'press',
            ],
            'local' => [
                'open today', 'open now', 'near me', 'nearby', 'around me', 'closest', 'pick up', 'pickup',
                'delivery to', 'in my area', 'zip code', 'zipcode', 'directions', 'address', 'hours',
                'local',
            ],
            'support' => [
                'not working', "doesn't work", 'doesnt work', 'reset password', 'forgotten password',
                'forgot password', 'troubleshoot', 'how to fix', 'how to use', 'uninstalled', 'uninstall',
                'failed', 'broken', 'stuck', 'error', 'manual', 'setup', 'install', 'cancel', 'refund', 'bug',
                'fix', 'slow',
            ],
            'utility' => [
                'free tool', 'online tool', 'downloader', 'generator', 'converter', 'calculator', 'checker',
                'tester', 'builder', 'scanner', 'viewer', 'editor', 'creator', 'maker', 'online', 'download',
                'tool', 'free',
            ],
            'informational' => [
                'whitepaper', 'case study', 'statistics', 'examples of', 'meaning of', 'history of',
                'when was', 'why does', 'what is', 'research', 'tutorial', 'stats', 'tips', 'ideas', 'how to',
            ],
        ];
    }

    /**
     * @return list<array<string, list<string>>>
     */
    private static function additionalLanguagePacks(): array
    {
        $fr = [
            'commercial' => ['meilleur choix', 'comparatif', 'alternative à', 'avis clients', 'note globale', 'lequel choisir', 'avantages et inconvénients', 'est-ce que ça vaut', 'arnaque ou fiable', 'meilleur', 'avis', 'comparateur'],
            'transactional' => ['acheter en ligne', 'prix promo', 'code promo', 'bon de réduction', 'commander', 'panier', 'livraison gratuite', 'abonnement', 'réservation', 'souscrire', 'tarif', 'paiement', 'promo', 'vente', 'louer', 'réserver'],
            'navigational' => ['espace client', 'mon compte', 'connexion', 'inscription', 'accueil', 'contact', 'service client', 'carrières', 'emploi', 'presse', 'portail'],
            'local' => ['près de chez moi', 'près de moi', 'horaires douverture', 'adresse', 'itinéraire', 'livraison à', 'click and collect', 'magasin le plus proche'],
            'support' => ['ne fonctionne pas', 'ne marche pas', 'erreur', 'plantage', 'désinstaller', 'réinitialiser', 'mot de passe oublié', 'bug', 'panne', 'trop lent', 'comment réparer', 'comment utiliser'],
            'utility' => ['télécharger', 'outil en ligne', 'générateur', 'convertisseur', 'calculateur', 'gratuit', 'essai gratuit', 'éditeur', 'scanner', 'testeur'],
            'informational' => ['comment faire', "qu'est-ce que", 'pourquoi', 'définition de', 'tutoriel', 'guide complet', 'exemples de', 'histoire de', 'statistiques', 'astuces'],
        ];
        $de = [
            'commercial' => ['alternativen', 'vergleich', 'testsieger', 'bewertungen', 'vor- und nachteile', 'lohnt sich', 'bester', 'vergleichen', 'testbericht'],
            'transactional' => ['kaufen', 'bestellen', 'preis', 'rabatt', 'gutschein', 'angebot', 'buchung', 'mieten', 'abonnement', 'kosten', 'günstig', 'sale'],
            'navigational' => ['login', 'anmelden', 'registrieren', 'konto', 'kundenservice', 'startseite', 'karriere', 'presse', 'portal'],
            'local' => ['in meiner nähe', 'öffnungszeiten', 'adresse', 'anfahrt', 'lieferung', 'abholung', 'filiale'],
            'support' => ['funktioniert nicht', 'fehler', 'deinstallieren', 'passwort vergessen', 'bug', 'langsam', 'wie repariert man', 'wie benutzt man'],
            'utility' => ['kostenlos', 'kostenloses tool', 'online tool', 'generator', 'konverter', 'rechner', 'herunterladen', 'editor'],
            'informational' => ['wie funktioniert', 'was ist', 'warum', 'bedeutung', 'tutorial', 'anleitung', 'tipps', 'statistik', 'geschichte'],
        ];
        $it = [
            'commercial' => ['migliore', 'recensioni', 'confronto', 'alternative', 'pro e contro', 'vale la pena', 'migliori'],
            'transactional' => ['acquistare', 'prezzo', 'sconto', 'coupon', 'ordine', 'carrello', 'abbonamento', 'prenotazione', 'affitto', 'vendita', 'offerta'],
            'navigational' => ['accedi', 'login', 'registrati', 'account', 'assistenza', 'homepage', 'lavora con noi', 'contatti'],
            'local' => ['vicino a me', 'orari', 'indirizzo', 'consegna', 'ritiro', 'aperto oggi'],
            'support' => ['non funziona', 'errore', 'disinstallare', 'password dimenticata', 'bug', 'lento', 'come riparare', 'come usare'],
            'utility' => ['scaricare', 'strumento online', 'generatore', 'convertitore', 'calcolatore', 'gratis', 'prova gratuita'],
            'informational' => ['come fare', 'cosè', 'perché', 'definizione', 'tutorial', 'guida', 'suggerimenti', 'statistiche'],
        ];
        $es = [
            'commercial' => ['mejor', 'reseñas', 'comparativa', 'alternativas', 'pros y contras', 'vale la pena', 'opiniones'],
            'transactional' => ['comprar', 'precio', 'descuento', 'cupón', 'pedido', 'carrito', 'suscripción', 'reservar', 'alquiler', 'venta', 'oferta'],
            'navigational' => ['iniciar sesión', 'registrarse', 'cuenta', 'atención al cliente', 'inicio', 'empleo', 'contacto', 'portal'],
            'local' => ['cerca de mí', 'horario', 'dirección', 'entrega', 'recogida', 'abierto hoy'],
            'support' => ['no funciona', 'error', 'desinstalar', 'olvidé mi contraseña', 'bug', 'lento', 'cómo arreglar', 'cómo usar'],
            'utility' => ['descargar', 'herramienta online', 'generador', 'convertidor', 'calculadora', 'gratis', 'prueba gratuita'],
            'informational' => ['cómo hacer', 'qué es', 'por qué', 'definición', 'tutorial', 'guía', 'consejos', 'estadísticas'],
        ];
        $pt = [
            'commercial' => ['melhor', 'comparativo', 'alternativas', 'avaliações', 'vale a pena', 'prós e contras'],
            'transactional' => ['comprar', 'preço', 'desconto', 'cupom', 'pedido', 'carrinho', 'assinatura', 'reservar', 'alugar', 'promoção'],
            'navigational' => ['entrar', 'login', 'cadastro', 'conta', 'suporte', 'início', 'carreiras', 'contato'],
            'local' => ['perto de mim', 'horário', 'endereço', 'entrega', 'retirada'],
            'support' => ['não funciona', 'erro', 'desinstalar', 'esqueci a senha', 'bug', 'lento', 'como consertar', 'como usar'],
            'utility' => ['baixar', 'ferramenta online', 'gerador', 'conversor', 'calculadora', 'grátis', 'teste grátis'],
            'informational' => ['como fazer', 'o que é', 'por que', 'definição', 'tutorial', 'guia', 'dicas', 'estatísticas'],
        ];
        $nl = [
            'commercial' => ['beste', 'review', 'vergelijking', 'alternatief', 'voordelen nadelen', 'de moeite waard'],
            'transactional' => ['kopen', 'prijs', 'korting', 'coupon', 'bestellen', 'winkelwagen', 'abonnement', 'huren', 'boeken'],
            'navigational' => ['inloggen', 'account', 'klantenservice', 'homepage', 'vacatures', 'contact'],
            'local' => ['in de buurt', 'openingstijden', 'adres', 'bezorgen', 'afhalen'],
            'support' => ['werkt niet', 'fout', 'deïnstalleren', 'wachtwoord vergeten', 'bug', 'traag', 'hoe repareer', 'hoe gebruik'],
            'utility' => ['downloaden', 'online tool', 'generator', 'converter', 'rekenmachine', 'gratis', 'proefperiode'],
            'informational' => ['hoe werkt', 'wat is', 'waarom', 'definitie', 'tutorial', 'gids', 'tips', 'statistieken'],
        ];
        $pl = [
            'commercial' => ['najlepszy', 'recenzje', 'porównanie', 'alternatywa', 'zalety wady', 'opłaca się'],
            'transactional' => ['kupić', 'cena', 'rabat', 'kupon', 'zamówienie', 'koszyk', 'subskrypcja', 'rezerwacja', 'wynajem'],
            'navigational' => ['logowanie', 'konto', 'obsługa klienta', 'strona główna', 'kariera', 'kontakt'],
            'local' => ['w pobliżu', 'godziny otwarcia', 'adres', 'dostawa', 'odbiór'],
            'support' => ['nie działa', 'błąd', 'odinstalować', 'zapomniałem hasła', 'bug', 'wolno', 'jak naprawić', 'jak używać'],
            'utility' => ['pobierz', 'narzędzie online', 'generator', 'konwerter', 'kalkulator', 'darmowy', 'darmowy trial'],
            'informational' => ['jak zrobić', 'co to', 'dlaczego', 'definicja', 'poradnik', 'porady', 'statystyki'],
        ];
        $ja = [
            'commercial' => ['比較', 'レビュー', 'おすすめ', '口コミ', 'ランキング', 'どっちがいい', 'メリット デメリット'],
            'transactional' => ['購入', '価格', '割引', 'クーポン', '注文', 'カート', '予約', 'サブスク', 'レンタル', '料金'],
            'navigational' => ['ログイン', 'マイページ', 'アカウント', 'サポート', '採用', 'お問い合わせ', 'ホーム'],
            'local' => ['近く', '営業時間', '住所', '配達', '店舗'],
            'support' => ['動かない', 'エラー', 'アンインストール', 'パスワード忘れ', 'バグ', '遅い', '直し方', '使い方'],
            'utility' => ['ダウンロード', '無料ツール', 'オンラインツール', 'ジェネレーター', '変換', '計算機', '無料'],
            'informational' => ['とは', 'やり方', '方法', '意味', 'チュートリアル', 'ガイド', '統計', 'ヒント'],
        ];
        $ko = [
            'commercial' => ['비교', '리뷰', '추천', '후기', '랭킹', '장단점', '가치'],
            'transactional' => ['구매', '가격', '할인', '쿠폰', '주문', '장바구니', '예약', '구독', '렌탈'],
            'navigational' => ['로그인', '회원가입', '계정', '고객센터', '채용', '문의', '홈'],
            'local' => ['근처', '영업시간', '주소', '배달', '픽업'],
            'support' => ['작동 안 함', '오류', '삭제', '비밀번호 분실', '버그', '느림', '고치는 방법', '사용법'],
            'utility' => ['다운로드', '무료 도구', '온라인 도구', '변환기', '계산기', '무료', '체험'],
            'informational' => ['방법', '뭐예요', '왜', '정의', '튜토리얼', '가이드', '통계', '팁'],
        ];
        $zh = [
            'commercial' => ['对比', '评测', '推荐', '哪个好', '优缺点', '值得买', '评价', '排名'],
            'transactional' => ['购买', '价格', '折扣', '优惠券', '下单', '购物车', '订阅', '预订', '租赁', '促销'],
            'navigational' => ['登录', '注册', '账户', '客服', '招聘', '联系我们', '首页'],
            'local' => ['附近', '营业时间', '地址', '配送', '自提', '门店'],
            'support' => ['无法使用', '错误', '卸载', '忘记密码', '故障', '很慢', '如何修复', '怎么用'],
            'utility' => ['下载', '在线工具', '生成器', '转换', '计算器', '免费', '试用'],
            'informational' => ['如何', '是什么', '为什么', '定义', '教程', '指南', '统计', '技巧'],
        ];
        $ar = [
            'commercial' => ['أفضل', 'مقارنة', 'بديل', 'مراجعات', 'إيجابيات سلبيات', 'هل يستحق'],
            'transactional' => ['شراء', 'سعر', 'خصم', 'كوبون', 'طلب', 'سلة', 'اشتراك', 'حجز', 'إيجار'],
            'navigational' => ['تسجيل الدخول', 'حساب', 'خدمة العملاء', 'الرئيسية', 'وظائف', 'اتصل بنا'],
            'local' => ['قريب مني', 'ساعات العمل', 'عنوان', 'توصيل', 'استلام'],
            'support' => ['لا يعمل', 'خطأ', 'إلغاء التثبيت', 'نسيت كلمة المرور', 'بطيء', 'كيفية إصلاح', 'كيفية استخدام'],
            'utility' => ['تحميل', 'أداة مجانية', 'محول', 'حاسبة', 'مجاني', 'تجربة مجانية'],
            'informational' => ['كيف', 'ما هو', 'لماذا', 'تعريف', 'شرح', 'دليل', 'إحصائيات', 'نصائح'],
        ];
        $tr = [
            'commercial' => ['en iyi', 'karşılaştırma', 'alternatif', 'yorum', 'artıları eksileri', 'buna değer mi'],
            'transactional' => ['satın al', 'fiyat', 'indirim', 'kupon', 'sipariş', 'sepet', 'abonelik', 'rezervasyon', 'kiralama'],
            'navigational' => ['giriş', 'kayıt', 'hesap', 'müşteri hizmetleri', 'ana sayfa', 'kariyer', 'iletişim'],
            'local' => ['yakınımda', 'çalışma saatleri', 'adres', 'teslimat', 'mağaza'],
            'support' => ['çalışmıyor', 'hata', 'kaldır', 'şifremi unuttum', 'yavaş', 'nasıl düzeltilir', 'nasıl kullanılır'],
            'utility' => ['indir', 'ücretsiz araç', 'dönüştürücü', 'hesap makinesi', 'ücretsiz', 'deneme'],
            'informational' => ['nasıl yapılır', 'nedir', 'neden', 'tanım', 'öğretici', 'rehber', 'istatistik', 'ipuçları'],
        ];
        $hi = [
            'commercial' => ['सर्वश्रेष्ठ', 'तुलना', 'विकल्प', 'समीक्षा', 'फायदे नुकसान', 'क्या फायदेमंद'],
            'transactional' => ['खरीदें', 'कीमत', 'छूट', 'कूपन', 'ऑर्डर', 'कार्ट', 'सदस्यता', 'बुकिंग'],
            'navigational' => ['लॉगिन', 'खाता', 'ग्राहक सेवा', 'होम', 'करियर', 'संपर्क'],
            'local' => ['पास में', 'समय', 'पता', 'डिलीवरी', 'पिकअप'],
            'support' => ['काम नहीं कर रहा', 'त्रुटि', 'अनइंस्टॉल', 'पासवर्ड भूल गए', 'धीमा', 'कैसे ठीक करें'],
            'utility' => ['डाउनलोड', 'मुफ्त टूल', 'कनवर्टर', 'कैलकुलेटर', 'मुफ्त', 'ट्रायल'],
            'informational' => ['कैसे', 'क्या है', 'क्यों', 'परिभाषा', 'ट्यूटोरियल', 'गाइड', 'सांख्यिकी', 'सुझाव'],
        ];
        $sv = [
            'commercial' => ['bäst', 'jämförelse', 'recension', 'för och nackdelar', 'värt det'],
            'transactional' => ['köp', 'pris', 'rabatt', 'kupong', 'beställ', 'varukorg', 'prenumeration', 'boka'],
            'navigational' => ['logga in', 'konto', 'kundservice', 'hem', 'karriär', 'kontakt'],
            'local' => ['nära mig', 'öppettider', 'adress', 'leverans', 'hämta'],
            'support' => ['fungerar inte', 'fel', 'avinstallera', 'glömt lösenord', 'långsamt', 'hur fixar', 'hur använder'],
            'utility' => ['ladda ner', 'gratis verktyg', 'generator', 'konverterare', 'kalkylator', 'gratis', 'provperiod'],
            'informational' => ['hur gör man', 'vad är', 'varför', 'definition', 'handledning', 'guide', 'statistik', 'tips'],
        ];
        $da = [
            'commercial' => ['bedst', 'sammenligning', 'anmeldelse', 'fordele ulemper', 'det værd'],
            'transactional' => ['køb', 'pris', 'rabat', 'kupon', 'bestil', 'kurv', 'abonnement', 'book'],
            'navigational' => ['log ind', 'konto', 'kundeservice', 'hjem', 'karriere', 'kontakt'],
            'local' => ['nær mig', 'åbningstider', 'adresse', 'levering', 'afhentning'],
            'support' => ['virker ikke', 'fejl', 'afinstaller', 'glemt adgangskode', 'langsom', 'hvordan fikser', 'hvordan bruger'],
            'utility' => ['download', 'gratis værktøj', 'generator', 'konverter', 'lommeregner', 'gratis', 'prøveperiode'],
            'informational' => ['hvordan', 'hvad er', 'hvorfor', 'definition', 'tutorial', 'guide', 'statistik', 'tips'],
        ];
        $no = [
            'commercial' => ['beste', 'sammenligning', 'anmeldelse', 'fordeler ulemper', 'verdt det'],
            'transactional' => ['kjøp', 'pris', 'rabatt', 'kupong', 'bestill', 'handlekurv', 'abonnement', 'bestill time'],
            'navigational' => ['logg inn', 'konto', 'kundeservice', 'hjem', 'karriere', 'kontakt'],
            'local' => ['nær meg', 'åpningstider', 'adresse', 'levering', 'henting'],
            'support' => ['fungerer ikke', 'feil', 'avinstaller', 'glemt passord', 'treg', 'hvordan fikse', 'hvordan bruke'],
            'utility' => ['last ned', 'gratis verktøy', 'generator', 'konverter', 'kalkulator', 'gratis', 'prøveperiode'],
            'informational' => ['hvordan', 'hva er', 'hvorfor', 'definisjon', 'veiledning', 'guide', 'statistikk', 'tips'],
        ];
        $fi = [
            'commercial' => ['paras', 'vertailu', 'arvostelu', 'edut haitat', 'kannattaako'],
            'transactional' => ['osta', 'hinta', 'alennus', 'kuponki', 'tilaa', 'ostoskori', 'tilaus', 'varaa'],
            'navigational' => ['kirjaudu', 'tili', 'asiakaspalvelu', 'etusivu', 'ura', 'yhteystiedot'],
            'local' => ['lähellä', 'aukiolo', 'osoite', 'toimitus', 'nouto'],
            'support' => ['ei toimi', 'virhe', 'poista asennus', 'unohdin salasanan', 'hidas', 'miten korjata', 'miten käyttää'],
            'utility' => ['lataa', 'ilmainen työkalu', 'generaattori', 'muunnin', 'laskin', 'ilmainen', 'kokeilu'],
            'informational' => ['miten', 'mikä on', 'miksi', 'määritelmä', 'opas', 'vinkit', 'tilastot'],
        ];
        $cs = [
            'commercial' => ['nejlepší', 'recenze', 'srovnání', 'alternativa', 'výhody nevýhody'],
            'transactional' => ['koupit', 'cena', 'sleva', 'kupón', 'objednat', 'košík', 'předplatné', 'rezervace'],
            'navigational' => ['přihlášení', 'účet', 'zákaznický servis', 'domů', 'kariéra', 'kontakt'],
            'local' => ['blízko mě', 'otevírací doba', 'adresa', 'doručení', 'vyzvednutí'],
            'support' => ['nefunguje', 'chyba', 'odinstalovat', 'zapomenuté heslo', 'pomalé', 'jak opravit', 'jak použít'],
            'utility' => ['stáhnout', 'nástroj online', 'generátor', 'konvertor', 'kalkulačka', 'zdarma', 'zkušební verze'],
            'informational' => ['jak na to', 'co je', 'proč', 'definice', 'návod', 'statistiky', 'tipy'],
        ];
        $el = [
            'commercial' => ['καλύτερο', 'σύγκριση', 'κριτικές', 'πλεονεκτήματα μειονεκτήματα', 'αξίζει'],
            'transactional' => ['αγορά', 'τιμή', 'έκπτωση', 'κουπόνι', 'παραγγελία', 'καλάθι', 'συνδρομή', 'κράτηση'],
            'navigational' => ['σύνδεση', 'λογαριασμός', 'υποστήριξη', 'αρχική', 'καριέρα', 'επικοινωνία'],
            'local' => ['κοντά μου', 'ώρες λειτουργίας', 'διεύθυνση', 'παράδοση', 'παραλαβή'],
            'support' => ['δεν λειτουργεί', 'σφάλμα', 'απεγκατάσταση', 'ξέχασα τον κωδικό', 'αργό', 'πως να διορθώσω'],
            'utility' => ['λήψη', 'δωρεάν εργαλείο', 'μετατροπέας', 'αριθμομηχανή', 'δωρεάν', 'δοκιμή'],
            'informational' => ['πως να', 'τι είναι', 'γιατί', 'ορισμός', 'οδηγός', 'στατιστικά', 'συμβουλές'],
        ];
        $he = [
            'commercial' => ['הכי טוב', 'השוואה', 'ביקורות', 'יתרונות חסרונות', 'שווה'],
            'transactional' => ['לקנות', 'מחיר', 'הנחה', 'קופון', 'הזמנה', 'עגלה', 'מנוי', 'הזמנה מראש'],
            'navigational' => ['התחברות', 'חשבון', 'שירות לקוחות', 'בית', 'קריירה', 'צור קשר'],
            'local' => ['קרוב אלי', 'שעות פתיחה', 'כתובת', 'משלוח', 'איסוף'],
            'support' => ['לא עובד', 'שגיאה', 'הסרה', 'שכחתי סיסמה', 'איטי', 'איך לתקן', 'איך להשתמש'],
            'utility' => ['הורדה', 'כלי חינם', 'ממיר', 'מחשבון', 'חינם', 'ניסיון'],
            'informational' => ['איך', 'מה זה', 'למה', 'הגדרה', 'מדריך', 'סטטיסטיקה', 'טיפים'],
        ];
        $th = [
            'commercial' => ['ดีที่สุด', 'รีวิว', 'เปรียบเทียบ', 'ข้อดีข้อเสีย', 'คุ้มค่า'],
            'transactional' => ['ซื้อ', 'ราคา', 'ส่วนลด', 'คูปอง', 'สั่งซื้อ', 'ตะกร้า', 'สมัครสมาชิก', 'จอง'],
            'navigational' => ['เข้าสู่ระบบ', 'บัญชี', 'บริการลูกค้า', 'หน้าแรก', 'สมัครงาน', 'ติดต่อ'],
            'local' => ['ใกล้ฉัน', 'เวลาเปิด', 'ที่อยู่', 'จัดส่ง', 'รับสินค้า'],
            'support' => ['ไม่ทำงาน', 'ข้อผิดพลาด', 'ถอนการติดตั้ง', 'ลืมรหัสผ่าน', 'ช้า', 'วิธีแก้', 'วิธีใช้'],
            'utility' => ['ดาวน์โหลด', 'เครื่องมือฟรี', 'ตัวแปลง', 'เครื่องคิดเลข', 'ฟรี', 'ทดลองใช้'],
            'informational' => ['วิธี', 'คืออะไร', 'ทำไม', 'คำจำกัดความ', 'สอน', 'คู่มือ', 'สถิติ', 'เคล็ดลับ'],
        ];
        $vi = [
            'commercial' => ['tốt nhất', 'so sánh', 'đánh giá', 'ưu nhược điểm', 'có đáng'],
            'transactional' => ['mua', 'giá', 'giảm giá', 'mã giảm giá', 'đặt hàng', 'giỏ hàng', 'đăng ký', 'đặt chỗ'],
            'navigational' => ['đăng nhập', 'tài khoản', 'hỗ trợ', 'trang chủ', 'tuyển dụng', 'liên hệ'],
            'local' => ['gần tôi', 'giờ mở cửa', 'địa chỉ', 'giao hàng', 'nhận hàng'],
            'support' => ['không hoạt động', 'lỗi', 'gỡ cài đặt', 'quên mật khẩu', 'chậm', 'cách sửa', 'cách dùng'],
            'utility' => ['tải xuống', 'công cụ miễn phí', 'chuyển đổi', 'máy tính', 'miễn phí', 'dùng thử'],
            'informational' => ['cách', 'là gì', 'tại sao', 'định nghĩa', 'hướng dẫn', 'thống kê', 'mẹo'],
        ];
        $id = [
            'commercial' => ['terbaik', 'perbandingan', 'ulasan', 'kelebihan kekurangan', 'layak'],
            'transactional' => ['beli', 'harga', 'diskon', 'kupon', 'pesan', 'keranjang', 'langganan', 'pesan tempat'],
            'navigational' => ['masuk', 'akun', 'layanan pelanggan', 'beranda', 'karir', 'kontak'],
            'local' => ['dekat saya', 'jam buka', 'alamat', 'pengiriman', 'ambil'],
            'support' => ['tidak berfungsi', 'kesalahan', 'hapus instalasi', 'lupa kata sandi', 'lambat', 'cara memperbaiki', 'cara menggunakan'],
            'utility' => ['unduh', 'alat gratis', 'konverter', 'kalkulator', 'gratis', 'uji coba'],
            'informational' => ['cara', 'apa itu', 'mengapa', 'definisi', 'tutorial', 'panduan', 'statistik', 'tips'],
        ];
        $ms = [
            'commercial' => ['terbaik', 'perbandingan', 'ulasan', 'kelebihan kelemahan', 'berbaloi'],
            'transactional' => ['beli', 'harga', 'diskaun', 'kupon', 'pesanan', 'troli', 'langganan', 'tempahan'],
            'navigational' => ['log masuk', 'akaun', 'khidmat pelanggan', 'utama', 'kerjaya', 'hubungi'],
            'local' => ['berdekatan', 'waktu operasi', 'alamat', 'penghantaran', 'ambil'],
            'support' => ['tidak berfungsi', 'ralat', 'nyahpasang', 'lupa kata laluan', 'perlahan', 'cara membaiki'],
            'utility' => ['muat turun', 'alat percuma', 'penukar', 'kalkulator', 'percuma', 'percubaan'],
            'informational' => ['cara', 'apa itu', 'kenapa', 'definisi', 'tutorial', 'panduan', 'statistik', 'tips'],
        ];
        $tl = [
            'commercial' => ['pinakamahusay', 'paghahambing', 'review', 'pros cons', 'sulit ba'],
            'transactional' => ['bumili', 'presyo', 'diskwento', 'kupon', 'order', 'cart', 'subscription', 'mag-book'],
            'navigational' => ['mag-login', 'account', 'customer service', 'home', 'careers', 'contact'],
            'local' => ['malapit sa akin', 'oras ng bukas', 'address', 'delivery', 'pickup'],
            'support' => ['hindi gumagana', 'error', 'i-uninstall', 'nakalimutan ang password', 'mabagal', 'paano ayusin'],
            'utility' => ['i-download', 'libreng tool', 'converter', 'calculator', 'libre', 'online tool'],
            'informational' => ['paano', 'ano ang', 'bakit', 'kahulugan', 'tutorial', 'guide', 'statistics', 'tips'],
        ];

        return [$fr, $de, $it, $es, $pt, $nl, $pl, $ja, $ko, $zh, $ar, $tr, $hi, $sv, $da, $no, $fi, $cs, $el, $he, $th, $vi, $id, $ms, $tl];
    }

    /**
     * @param  list<string>  $phrases
     * @return list<string>
     */
    private static function sortTriggersDesc(array $phrases): array
    {
        $normalized = [];
        foreach ($phrases as $p) {
            $p = mb_strtolower(trim((string) $p), 'UTF-8');
            if ($p !== '') {
                $normalized[] = $p;
            }
        }
        $normalized = array_values(array_unique($normalized));
        usort($normalized, fn (string $a, string $b) => mb_strlen($b, 'UTF-8') <=> mb_strlen($a, 'UTF-8'));

        return $normalized;
    }
}
