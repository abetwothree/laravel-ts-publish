export type BroadcastChannel =
    | `orders.${string | number}`
    | `user.${string | number}.notifications`
    | `chat.${string | number}.messages`
    | `public-announcements`
    | `order.${string | number}`
    | `post.${string | number}.comment.${string | number}`
    | `order-status.${string | number}`
    | `color-theme.${string | number}`
    | `role-dashboard.${string | number}`
    | `teams.${string | number}.rooms.${string | number}`;

export const BroadcastChannels = {
    orders: (orderId: string | number) => `orders.${orderId}` as const,
    user: (userId: string | number) => ({
        notifications: `user.${userId}.notifications` as const,
    }),
    chat: (roomId: string | number) => ({
        messages: `chat.${roomId}.messages` as const,
    }),
    "public-announcements": `public-announcements` as const,
    order: (orderId: string | number) => `order.${orderId}` as const,
    post: (postId: string | number) => ({
        comment: (commentId: string | number) => `post.${postId}.comment.${commentId}` as const,
    }),
    "order-status": (statusId: string | number) => `order-status.${statusId}` as const,
    "color-theme": (colorId: string | number) => `color-theme.${colorId}` as const,
    "role-dashboard": (roleId: string | number) => `role-dashboard.${roleId}` as const,
    teams: (teamId: string | number) => ({
        rooms: (roomName: string | number) => `teams.${teamId}.rooms.${roomName}` as const,
    }),
};
