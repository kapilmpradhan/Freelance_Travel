<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AgentToken extends Model
{
    use HasFactory;

    protected $table = 'agent_tokens';
    protected $fillable = ['agent_name', 'agent_id', 'email_address', 'status', 'linked_on', 'agent_token'];

    public function rule()
    {
        return [
            'agent_name' => 'required',
            'agent_id' => 'required',
            'email_address' => 'required',
            'status' => 'required',
            'linked_on' => 'required',
            'agent_token' => 'required'
        ];
    }

    public function storeAgentToken($request)
    {
        $data = $request->all();
        return $this->create($data);
    }
}