export type BroadcastChannel =
    | `orders.${string | number}`
    | `user.${string | number}.notifications`
    | `chat.${string | number}.messages`
    | `public-announcements`;

export const BroadcastChannels = {
    orders: (orderId: string | number) => `orders.${orderId}` as const,
    user: (userId: string | number) => ({
        notifications: `user.${userId}.notifications` as const,
    }),
    chat: (roomId: string | number) => ({
        messages: `chat.${roomId}.messages` as const,
    }),
    "public-announcements": `public-announcements` as const,
};
