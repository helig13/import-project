
@extends('layouts.app')

@section('title', 'Page Title')

@section('content')
    <h1>Upload Excel</h1>
    <div class="container">
        <form action="{{ url('/upload') }}" method="post" enctype="multipart/form-data">
            @csrf
            <div class="form-group">
                <label for="file">Select Excel File</label>
                <input type="file" name="file" id="file" class="form-control" accept=".xlsx,.xls,.csv">
            </div>
            <button type="submit" class="btn btn-primary">Upload</button>
        </form>
    </div>

    <div class="progress-container">
        <div id="progressBar" class="progress-bar"></div>
    </div>
    <p>Total Rows: <span id="totalFileRows">0</span></p>
    <p>Skipped Invalid Rows: <span id="skippedCount">0</span></p>
    <p>Duplicate Rows: <span id="duplicateCount">0</span></p>
    <p>Time taken: <span id="processingTime">0</span></p>

@endsection

