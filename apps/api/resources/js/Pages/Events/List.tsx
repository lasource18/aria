import React from 'react';
import { Head, Link } from '@inertiajs/react';

interface Org {
    id: string;
    name: string;
}

interface Event {
    id: string;
    title: string;
    venue_name: string;
    status: string;
}

interface Props {
    org: Org;
    events: Event[];
}

export default function EventsList({ org, events }: Props) {
    return (
        <>
            <Head title="Events" />

            <div className="min-h-screen bg-gray-100">
                <div className="py-12">
                    <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
                        <div className="flex justify-between items-center mb-6">
                            <h1 className="text-3xl font-bold text-gray-900">
                                Events
                            </h1>
                            <Link
                                href={`/org/${org.id}/events/create`}
                                className="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                            >
                                Create Event
                            </Link>
                        </div>

                        {events.length === 0 ? (
                            <div className="bg-white shadow sm:rounded-lg">
                                <div className="px-4 py-5 sm:p-6 text-center">
                                    <svg
                                        className="mx-auto h-12 w-12 text-gray-400"
                                        fill="none"
                                        viewBox="0 0 24 24"
                                        stroke="currentColor"
                                    >
                                        <path
                                            strokeLinecap="round"
                                            strokeLinejoin="round"
                                            strokeWidth={2}
                                            d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"
                                        />
                                    </svg>
                                    <h3 className="mt-2 text-sm font-medium text-gray-900">No events</h3>
                                    <p className="mt-1 text-sm text-gray-500">
                                        Get started by creating a new event.
                                    </p>
                                    <div className="mt-6">
                                        <Link
                                            href={`/org/${org.id}/events/create`}
                                            className="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                                        >
                                            Create Event
                                        </Link>
                                    </div>
                                </div>
                            </div>
                        ) : (
                            <div className="grid gap-4">
                                {events.map((event) => (
                                    <div
                                        key={event.id}
                                        className="bg-white p-6 rounded-lg shadow hover:shadow-md transition-shadow"
                                    >
                                        <div className="flex justify-between items-start">
                                            <div>
                                                <h3 className="text-xl font-semibold text-gray-900">
                                                    {event.title}
                                                </h3>
                                                <p className="text-gray-600 mt-2">
                                                    {event.venue_name}
                                                </p>
                                                <span className="inline-block mt-2 px-3 py-1 text-sm rounded-full bg-gray-200 text-gray-800">
                                                    {event.status}
                                                </span>
                                            </div>
                                            <Link
                                                href={`/org/${org.id}/events/${event.id}/edit`}
                                                className="text-blue-600 hover:text-blue-800 text-sm font-medium"
                                            >
                                                Edit
                                            </Link>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </>
    );
}
