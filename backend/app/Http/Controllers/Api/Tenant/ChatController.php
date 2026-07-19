<?php

namespace App\Http\Controllers\Api\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Chat;
use App\Models\ChatMessage;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChatController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $chats = Chat::where('user_id', $userId)
            ->orWhere('assigned_to', $userId)
            ->with(['lastMessage', 'customer:id,full_name'])
            ->orderByDesc('updated_at')
            ->paginate($request->input('per_page', 25));

        return ApiResponse::paginated($chats);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'customer_id' => ['nullable', 'integer'],
            'subject' => ['nullable', 'string', 'max:200'],
        ]);

        $chat = Chat::create([
            'pdam_org_id' => $request->user()->pdam_org_id,
            'user_id' => $request->user()->id,
            'customer_id' => $data['customer_id'] ?? null,
            'subject' => $data['subject'] ?? null,
        ]);

        return ApiResponse::success($chat, status: 201);
    }

    public function messages(Request $request, Chat $chat): JsonResponse
    {
        $messages = ChatMessage::where('chat_id', $chat->id)
            ->orderBy('created_at')
            ->get();

        ChatMessage::where('chat_id', $chat->id)
            ->where('sender_id', '!=', $request->user()->id)
            ->where('is_read', false)
            ->update(['is_read' => true]);

        return ApiResponse::success($messages);
    }

    public function sendMessage(Request $request, Chat $chat): JsonResponse
    {
        $data = $request->validate([
            'message' => ['required', 'string'],
            'attachments' => ['nullable', 'array'],
        ]);

        $msg = ChatMessage::create([
            'chat_id' => $chat->id,
            'sender_id' => $request->user()->id,
            'sender_type' => 'user',
            'message' => $data['message'],
            'attachments' => $data['attachments'] ?? null,
        ]);

        $chat->touch();

        return ApiResponse::success($msg, status: 201);
    }
}
