<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use Illuminate\Http\Request;

class AuditController extends Controller
{
    public function __invoke(Request $request)
    {
        $log = ActivityLog::with('user:id,name')
            ->when($request->filled('aksi'), fn ($q) => $q->where('action', $request->aksi))
            ->when($request->filled('objek'), fn ($q) => $q->where('subject_type', 'like', '%'.$request->objek))
            ->latest('id')->paginate(50)->withQueryString();

        return view('audit.index', compact('log'));
    }
}
