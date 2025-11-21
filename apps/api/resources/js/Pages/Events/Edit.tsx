import React from 'react';
import { Head, useForm } from '@inertiajs/react';

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
    event: Event;
}

export default function EventsEdit({ org, event }: Props) {
    const { data, setData, put, processing, errors } = useForm({
        title: event.title || '',
        venue_name: event.venue_name || '',
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        put(`/api/v1/orgs/${org.id}/events/${event.id}`);
    };

    return (
        <>
            <Head title="Edit Event" />

            <div className="min-h-screen bg-gray-100">
                <div className="py-12">
                    <div className="max-w-3xl mx-auto sm:px-6 lg:px-8">
                        <div className="bg-white shadow sm:rounded-lg">
                            <div className="px-4 py-5 sm:p-6">
                                <h1 className="text-3xl font-bold text-gray-900 mb-6">
                                    Edit Event
                                </h1>

                                <form onSubmit={handleSubmit} className="space-y-6">
                                    <div>
                                        <label htmlFor="title" className="block text-sm font-medium text-gray-700">
                                            Event Title
                                        </label>
                                        <input
                                            type="text"
                                            id="title"
                                            value={data.title}
                                            onChange={(e) => setData('title', e.target.value)}
                                            className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                                        />
                                        {errors.title && (
                                            <p className="mt-1 text-sm text-red-600">
                                                {errors.title}
                                            </p>
                                        )}
                                    </div>

                                    <div>
                                        <label htmlFor="venue_name" className="block text-sm font-medium text-gray-700">
                                            Venue Name
                                        </label>
                                        <input
                                            type="text"
                                            id="venue_name"
                                            value={data.venue_name}
                                            onChange={(e) => setData('venue_name', e.target.value)}
                                            className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                                        />
                                        {errors.venue_name && (
                                            <p className="mt-1 text-sm text-red-600">
                                                {errors.venue_name}
                                            </p>
                                        )}
                                    </div>

                                    <div className="flex justify-end">
                                        <button
                                            type="submit"
                                            disabled={processing}
                                            className="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed"
                                        >
                                            {processing ? 'Saving...' : 'Save Changes'}
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </>
    );
}
