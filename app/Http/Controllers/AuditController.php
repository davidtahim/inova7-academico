<?php

namespace App\Http\Controllers;

use App\Models\AuditDocument;
use App\Models\AuditItem;
use App\Models\AuditTemplate;

class AuditController extends Controller
{
    public function index()
    {
        return view('audit.index', ['items' => AuditItem::with('templates')->orderBy('code')->get(), 'documents' => AuditDocument::with('template')->latest()->limit(10)->get()]);
    }
}
