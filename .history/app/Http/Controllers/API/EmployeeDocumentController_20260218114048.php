<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\EmployeeDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;

class EmployeeDocumentController extends Controller
{
    /**
     * Get all documents for an employee
     */
    public function index($userId)
    {
        try {
            $documents = EmployeeDocument::where('user_id', $userId)
                ->with('uploadedBy')
                ->latest()
                ->get();

            return response()->json([
                'success' => true,
                'data' => $documents
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch documents',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Upload document
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'document_type' => 'required|in:Contract,ID Copy,Passport,Work Permit,Visa,Certificate,Other',
            'document_name' => 'required|string|max:255',
            'document' => 'required|file|mimes:pdf,jpg,jpeg,png,doc,docx|max:10240',
            'issue_date' => 'nullable|date',
            'expiry_date' => 'nullable|date|after:issue_date',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Upload file
            $file = $request->file('document');
            $fileName = time() . '_' . $file->getClientOriginalName();
            $filePath = $file->storeAs('employee_documents', $fileName, 'public');

            $document = EmployeeDocument::create([
                'user_id' => $request->user_id,
                'document_type' => $request->document_type,
                'document_name' => $request->document_name,
                'document_path' => $filePath,
                'issue_date' => $request->issue_date,
                'expiry_date' => $request->expiry_date,
                'notes' => $request->notes,
                'uploaded_by' => auth()->id(),
            ]);

            $document->load('uploadedBy');

            return response()->json([
                'success' => true,
                'message' => 'Document uploaded successfully',
                'data' => $document
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to upload document',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete document
     */
    public function destroy($id)
    {
        try {
            $document = EmployeeDocument::findOrFail($id);

            // Delete file
            Storage::disk('public')->delete($document->document_path);

            $document->delete();

            return response()->json([
                'success' => true,
                'message' => 'Document deleted successfully'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete document',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get expiring documents (within 30 days)
     */
    public function getExpiringDocuments()
    {
        try {
            $documents = EmployeeDocument::with(['user', 'uploadedBy'])
                ->whereNotNull('expiry_date')
                ->whereDate('expiry_date', '>=', now())
                ->whereDate('expiry_date', '<=', now()->addDays(30))
                ->orderBy('expiry_date', 'asc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $documents,
                'count' => $documents->count()
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch expiring documents',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get expired documents
     */
    public function getExpiredDocuments()
    {
        try {
            $documents = EmployeeDocument::with(['user', 'uploadedBy'])
                ->whereNotNull('expiry_date')
                ->whereDate('expiry_date', '<', now())
                ->orderBy('expiry_date', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $documents,
                'count' => $documents->count()
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch expired documents',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}