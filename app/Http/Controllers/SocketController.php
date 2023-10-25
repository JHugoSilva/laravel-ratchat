<?php

namespace App\Http\Controllers;

use App\Models\Chat;
use App\Models\ChatRequest;
use App\Models\User;
use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;

use Illuminate\Http\Request;

class SocketController extends Controller implements MessageComponentInterface
{
    protected $clients;

    public function __construct()
    {
        $this->clients = new \SplObjectStorage;
    }

    public function onOpen(ConnectionInterface $conn)
    {
        $this->clients->attach($conn);
        $querystring = $conn->httpRequest->getUri()->getQuery();
        parse_str($querystring, $queryarray);
        if (isset($queryarray['token'])) {
            User::where('token', $queryarray['token'])->update([
                'connection_id' => $conn->resourceId
            ]);
        }
    }

    public function onMessage(ConnectionInterface $conn, $msg)
    {
        $data = json_decode($msg);
        if (isset($data->type)) {
            if ($data->type == 'request_load_unconnected_user') {
                $user_data = User::select('id', 'name', 'user_status', 'user_image')
                    ->where('id', '!=', $data->from_user_id)
                    ->orderBy('name', 'ASC')->get();

                $sub_data = [];

                foreach ($user_data as $row) {
                    $sub_data[] = [
                        'name' => $row['name'],
                        'id' => $row['id'],
                        'user_status' => $row['user_status'],
                        'user_image' => $row['user_image'],
                    ];
                }

                $sender_connection_id = User::select('connection_id')->where('id', $data->from_user_id)->get();
                $send_data['data'] = $sub_data;
                $send_data['response_load_unconnected_user'] = true;

                foreach ($this->clients as $client) {
                    if ($client->resourceId == $sender_connection_id[0]->connection_id) {
                        $client->send(json_encode($send_data));
                    }
                }
            }

            if ($data->type == 'request_search_user') {
                $user_data = User::select('id', 'name', 'user_status', 'user_image')
                    ->where('id', '!=', $data->from_user_id)
                    ->where('name', 'like', '%' . $data->search_query . '%')
                    ->orderBy('name', 'ASC')
                    ->get();

                $sub_data = [];

                foreach ($user_data as $row) {

                    $chatRequest = ChatRequest::select('id')
                        ->where(function ($query) use ($data, $row) {
                            $query->where('from_user_id', $data->from_user_id)
                                ->where('to_user_id', $row->id);
                        })
                        ->orWhere(function ($query) use ($data, $row) {
                            $query->where('from_user_id', $row->id)
                                ->where('to_user_id', $data->from_user_id);
                        })
                        ->get();

                    if ($chatRequest->count() == 0) {
                        $sub_data[] = [
                            'name' => $row['name'],
                            'id' => $row['id'],
                            'status' => $row['user_status'],
                            'user_image' => $row['user_image'],
                        ];
                    }
                }

                $sender_connection_id = User::select('connection_id')
                    ->where('id', $data->from_user_id)
                    ->get();

                $send_data['data'] = $sub_data;
                $send_data['response_search_user'] = true;

                foreach ($this->clients as $client) {
                    if ($client->resourceId == $sender_connection_id[0]->connection_id) {
                        $client->send(json_encode($send_data));
                    }
                }
            }

            if ($data->type == 'request_chat_user') {
                $chatRequest = new ChatRequest();
                $chatRequest->from_user_id = $data->from_user_id;
                $chatRequest->to_user_id = $data->to_user_id;
                $chatRequest->status = 'Pending';
                $chatRequest->save();

                $sender_connection_id = User::select('connection_id')
                    ->where('id', $data->from_user_id)
                    ->get();

                $receiver_connection_id = User::select('connection_id')
                    ->where('id', $data->to_user_id)
                    ->get();

                foreach ($this->clients as $client) {
                    if ($client->resourceId == $sender_connection_id[0]->connection_id) {
                        $send_data['response_from_user_chat_request'] = true;
                        $client->send(json_encode($send_data));
                    }
                    if ($client->resourceId == $receiver_connection_id[0]->connection_id) {
                        $send_data['user_id'] = $data->to_user_id;
                        $send_data['response_to_user_chat_request'] = true;
                        $client->send(json_encode($send_data));
                    }
                }

            }

            if ($data->type == 'request_load_unread_notification') {
                $notificationData = ChatRequest::select('id', 'from_user_id', 'to_user_id', 'status')
                    ->where('status', '!=', 'Approve')
                    ->where(function ($query) use ($data) {
                        $query->where('from_user_id', $data->user_id)
                            ->orWhere('to_user_id', $data->user_id);
                    })->orderBy('id', 'ASC')
                    ->get();

                $sub_data = [];

                foreach ($notificationData as $row) {
                    $user_id = '';
                    $notification_type = '';

                    if ($row->from_user_id == $data->user_id) {
                        $user_id = $row->to_user_id;
                        $notification_type = 'Send Request';
                    } else {
                        $user_id = $row->from_user_id;
                        $notification_type = 'Receive Request';
                    }

                    $user_data = User::select('name', 'user_image')
                        ->where('id', $user_id)
                        ->first();

                    $sub_data[] = [
                        'id' => $row->id,
                        'from_user_id' => $row->from_user_id,
                        'to_user_id' => $row->to_user_id,
                        'name' => $user_data->name,
                        'notification_type' => $notification_type,
                        'status' => $row->status,
                        'user_image' => $user_data->user_image,
                    ];
                }

                $sender_connection_id = User::select('connection_id')
                    ->where('id', $data->user_id)
                    ->get();

                foreach ($this->clients as $client) {
                    if ($client->resourceId == $sender_connection_id[0]->connection_id) {
                        $send_data['response_load_notification'] = true;
                        $send_data['data'] = $sub_data;
                        $client->send(json_encode($send_data));
                    }
                }
            }

            if ($data->type == 'request_process_chat_request') {
                ChatRequest::where('id', $data->chat_request_id)
                    ->update(['status' => $data->action]);

                $sender_connection_id = User::select('connection_id')
                    ->where('id', $data->from_user_id)->get();

                $receiver_connection_id = User::select('connection_id')
                    ->where('id', $data->to_user_id)->get();

                foreach ($this->clients as $client) {
                    $send_data['response_process_chat_request'] = true;

                    if ($client->resourceId == $sender_connection_id[0]->connection_id) {
                        $send_data['data'] = $data->from_user_id;
                    }

                    if ($client->resourceId == $receiver_connection_id[0]->connection_id) {
                        $send_data['data'] = $data->to_user_id;
                    }

                    $client->send(json_encode($send_data));
                }
            }

            if ($data->type == 'request_connected_chat_user') {
                $condition_1 = [
                    'from_user_id' => $data->from_user_id,
                    'to_user_id' => $data->from_user_id
                ];

                $user_id_data = ChatRequest::select('from_user_id', 'to_user_id')
                    ->orWhere($condition_1)
                    ->where('status', 'Approve')
                    ->get();

                $sub_data = [];

                foreach ($user_id_data as $user_id_row) {
                    $user_id = '';

                    if ($user_id_row->from_user_id != $data->from_user_id) {
                        $user_id = $user_id_row->from_user_id;
                    } else {
                       $user_id = $user_id_row->to_user_id;
                    }

                    $user_data = User::select('id', 'name', 'user_image')
                        ->where('id', $user_id)
                        ->first();

                    $sub_data[] = [
                        'id' => $user_data->id,
                        'name' => $user_data->name,
                        'user_image' => $user_data->user_image
                    ];

                    $sender_connection_id = User::select('connection_id')
                        ->where('id', $data->from_user_id)
                        ->get();

                    foreach ($this->clients as $client) {
                        if ($client->resourceId == $sender_connection_id[0]->connection_id) {
                            $send_data['response_connected_chat_user'] = true;
                            $send_data['data'] = $sub_data;
                            $client->send(json_encode($send_data));
                        }
                    }

                }
            }

            if ($data->type == 'request_send_message') {

                $chat = new Chat();
                $id = explode(',', $data->to_user_id);
                $chat->from_user_id = $data->from_user_id;
                $chat->to_user_id = $id[0];
                $chat->chat_message = $data->message;
                $chat->message_status = 'Not Send';
                $chat->save();

                $chat_message_id = $chat->id;

                $receiver_connection_id = User::select('connection_id')
                    ->where('id', $data->to_user_id)
                    ->get();

                $sender_connection_id = User::select('connection_id')
                    ->where('id', $data->from_user_id)
                    ->get();

                foreach ($this->clients as $client) {

                    if ($client->resourceId == $receiver_connection_id[0]->connection_id || $client->resourceId == $sender_connection_id[0]->connection_id) {
                        $send_data['message'] = $data->message;
                        $send_data['from_user_id'] = $data->from_user_id;
                        $send_data['to_user_id'] = $data->to_user_id;
                        if ($client->resourceId == $receiver_connection_id[0]->connection_id) {
                            Chat::where('id', $chat_message_id)->update([
                                'message_status' => 'Send'
                            ]);
                            $send_data['message_status'] = 'Send';
                        } else {
                            $send_data['message_status'] = 'Not Send';
                        }

                        $client->send(json_encode($send_data));
                    }
                }
            }

            if ($data->type == 'request_chat_history') {
                $chatData = Chat::select('id', 'from_user_id', 'to_user_id', 'chat_message', 'message_status')
                    ->where(function($query) use ($data){
                        $query->where('from_user_id', $data->from_user_id)
                            ->where('to_user_id', $data->to_user_id);
                    })->orWhere(function($query) use($data) {
                        $query->where('from_user_id', $data->to_user_id)
                            ->where('to_user_id', $data->from_user_id);
                    })
                        ->orderBy('id', 'ASC')
                            ->get();

                $send_data['chat_history'] = $chatData;
                $receiver_connection_id = User::select('connection_id')
                    ->where('id', $data->from_user_id)
                    ->get();

                foreach ($this->clients as $client) {
                    if ($client->resourceId == $receiver_connection_id[0]->connection_id) {
                        $client->send(json_encode($send_data));
                    }
                }
            }

            if ($data->type == 'update_chat_status') {
                Chat::where('id', $data->chat_message_id)
                    ->update(['message_status' => $data->chat_message_status]);

                $sender_connection_id = User::select('connection_id')->where('id', $data->from_user_id)->get();

                foreach ($this->clients as $client) {
                    if ($client->resourceId == $sender_connection_id[0]->connection_id) {
                        $send_data['update_message_status'] = $data->chat_message_status;
                        $send_data['chat_message_id'] = $data->chat_message_id;
                        $client->send(json_encode($send_data));
                     }
                }
            }

            if ($data->type == 'check_unread_message') {
                $chatData = Chat::select('id', 'from_user_id', 'to_user_id')
                    ->where('message_status', '!=', 'Read')
                    ->where('from_user_id', $data->to_user_id)->get();

                $sender_connection_id = User::select('connection_id')
                    ->where('id', $data->from_user_id)
                    ->get();

                $receiver_connection_id = User::select('connection_id')
                    ->where('id', $data->to_user_id)
                    ->get();

                foreach ($chatData as $row) {
                    Chat::where('id', $row->id)->update([
                        'message_status' => 'Send'
                    ]);

                    foreach ($this->clients as $client) {
                        if ($client->resourceId == $sender_connection_id[0]->connection_id) {
                            $send_data['count_unread_message'] = 1;
                            $send_data['chat_message_id'] = $row->id;
                            $send_data['from_user_id'] = $row->from_user_id;
                        }

                        if ($client->resourceId == $receiver_connection_id[0]->connection_id) {
                            $send_data['update_message_status'] = 'Send';
                            $send_data['chat_message_id'] = $row->id;
                            $send_data['unread_msg'] = 1;
                            $send_data['from_user_id'] = $row->from_user_id;
                        }

                        $client->send(json_encode($send_data));
                    }
                }

            }
        }
    }

    public function onClose(ConnectionInterface $conn)
    {
        $this->clients->detach($conn);
        $querystring = $conn->httpRequest->getUri()->getQuery();
        parse_str($querystring, $queryarray);
        if (isset($queryarray['token'])) {
            User::where('token', $queryarray['token'])->update([
                'connection_id' => $conn->resourceId
            ]);
        }
    }

    public function onError(ConnectionInterface $conn, \Exception $e)
    {
        echo "An error has ocurred:{$e->getMessage()} - {$e->getLine()}";
        $conn->close();
    }
}
