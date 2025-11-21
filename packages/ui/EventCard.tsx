import React from 'react';
import { formatEventDate } from '@aria/utils';

export interface Event {
    id: string;
    title: string;
    venue_name: string;
    start_at: string;
    status: string;
}

interface EventCardProps {
    event: Event;
    onView?: () => void;
}

export function EventCard({ event, onView }: EventCardProps) {

    return (
        <div className="bg-white rounded-lg shadow-md overflow-hidden hover:shadow-lg transition-shadow">
            <div className="p-6">
                <h3 className="text-xl font-semibold text-gray-900">
                    {event.title}
                </h3>
                <p className="mt-2 text-gray-600">
                    {event.venue_name}
                </p>
                {event.start_at && (
                    <p className="mt-1 text-sm text-gray-500">
                        {formatEventDate(event.start_at)}
                    </p>
                )}
                <span className={`inline-block mt-3 px-3 py-1 text-xs rounded-full ${
                    event.status === 'published' ? 'bg-green-100 text-green-800' :
                    event.status === 'draft' ? 'bg-gray-100 text-gray-800' :
                    'bg-red-100 text-red-800'
                }`}>
                    {event.status}
                </span>
                {onView && (
                    <button
                        onClick={onView}
                        className="mt-4 text-blue-600 hover:text-blue-800 text-sm font-medium"
                    >
                        View Details →
                    </button>
                )}
            </div>
        </div>
    );
}
