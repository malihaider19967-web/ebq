<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

/**
 * Admin "Crawler" panel: a fleet-wide, live view of every shared crawl_site's
 * crawl progress (status, per-cap progress, crawled pages, errors, open issues,
 * health) plus the live crawl-queue backlog. The data + polling live in the
 * App\Livewire\Admin\CrawlerProgress component.
 */
class CrawlerController extends Controller
{
    public function index(): View
    {
        return view('admin.crawler.index');
    }
}
