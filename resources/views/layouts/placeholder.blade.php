@extends('layouts.app')

@section('title', $title)
@section('header', $title)

@section('content')
    <div class="bg-white rounded-xl border border-slate-200 p-8 text-center">
        <p class="text-slate-600 text-lg">تم تفعيل المسار والصلاحيات بنجاح لشاشة: <strong>{{ $title }}</strong></p>
    </div>
@endsection