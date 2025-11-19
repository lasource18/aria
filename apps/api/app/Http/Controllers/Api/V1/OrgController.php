<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Org;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class OrgController extends Controller
{
    /**
     * Get all organizations for the authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Org::class);

        $orgs = $request->user()->orgs()->with('members')->get();

        return response()->json([
            'data' => $orgs,
        ]);
    }

    /**
     * Create a new organization.
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Org::class);

        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'country_code' => ['sometimes', 'string', 'size:2'],
            'payout_channel' => ['sometimes', 'string', 'in:orange_mo,mtn_momo,wave,bank'],
            'payout_identifier' => ['sometimes', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                    'message' => 'Validation failed',
                    'details' => $validator->errors(),
                ],
            ], 422);
        }

        $org = Org::create($request->only(['name', 'country_code', 'payout_channel', 'payout_identifier']));

        // Auto-assign creator as owner
        $org->addMember($request->user(), 'owner');

        return response()->json([
            'data' => $org->load('members'),
        ], 201);
    }

    /**
     * Get a specific organization.
     */
    public function show(Org $org): JsonResponse
    {
        $this->authorize('view', $org);

        return response()->json([
            'data' => $org->load('members.user'),
        ]);
    }

    /**
     * Update an organization.
     */
    public function update(Request $request, Org $org): JsonResponse
    {
        $this->authorize('update', $org);

        $validator = Validator::make($request->all(), [
            'name' => ['sometimes', 'string', 'max:255'],
            'country_code' => ['sometimes', 'string', 'size:2'],
            'payout_channel' => ['sometimes', 'string', 'in:orange_mo,mtn_momo,wave,bank'],
            'payout_identifier' => ['sometimes', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                    'message' => 'Validation failed',
                    'details' => $validator->errors(),
                ],
            ], 422);
        }

        $org->update($request->only(['name', 'country_code', 'payout_channel', 'payout_identifier']));

        return response()->json([
            'data' => $org,
        ]);
    }

    /**
     * Add a member to the organization.
     */
    public function addMember(Request $request, Org $org): JsonResponse
    {
        $this->authorize('addMember', $org);

        $validator = Validator::make($request->all(), [
            'user_id' => ['required', 'uuid', 'exists:users,id'],
            'role' => ['required', 'string', 'in:owner,admin,staff,finance'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                    'message' => 'Validation failed',
                    'details' => $validator->errors(),
                ],
            ], 422);
        }

        $user = User::findOrFail($request->user_id);

        // Check if user is already a member
        if ($org->hasMember($user)) {
            return response()->json([
                'error' => [
                    'code' => 'ALREADY_MEMBER',
                    'message' => 'User is already a member of this organization',
                ],
            ], 422);
        }

        $member = $org->addMember($user, $request->role);

        return response()->json([
            'data' => $member->load('user'),
        ], 201);
    }

    /**
     * Remove a member from the organization.
     */
    public function removeMember(Request $request, Org $org, string $userId): JsonResponse
    {
        $this->authorize('removeMember', $org);

        $user = User::findOrFail($userId);

        // Prevent removing last owner
        if ($org->isLastOwner($user)) {
            return response()->json([
                'error' => [
                    'code' => 'LAST_OWNER',
                    'message' => 'Cannot remove the last owner of the organization',
                ],
            ], 422);
        }

        $org->removeMember($user);

        return response()->json([
            'data' => [
                'message' => 'Member removed successfully',
            ],
        ]);
    }

    /**
     * Update a member's role.
     */
    public function updateMemberRole(Request $request, Org $org, string $userId): JsonResponse
    {
        $this->authorize('updateMemberRole', $org);

        $validator = Validator::make($request->all(), [
            'role' => ['required', 'string', 'in:owner,admin,staff,finance'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                    'message' => 'Validation failed',
                    'details' => $validator->errors(),
                ],
            ], 422);
        }

        $user = User::findOrFail($userId);

        $org->updateMemberRole($user, $request->role);

        return response()->json([
            'data' => [
                'message' => 'Member role updated successfully',
            ],
        ]);
    }
}
