import React from 'react';
import { Head, useForm } from '@inertiajs/react';

interface Org {
    id: string;
    name: string;
}

interface Props {
    org: Org;
}

export default function EventsCreate({ org }: Props) {
    const { data, setData, post, processing, errors } = useForm({
        title: '',
        description_md: '',
        venue_name: '',
        start_at: '',
        end_at: '',
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post(`/api/v1/orgs/${org.id}/events`);
    };

    return (
        <>
            <Head title="Create Event" />

            <div className="min-h-screen bg-gray-100">
                <div className="py-12">
                    <div className="max-w-3xl mx-auto sm:px-6 lg:px-8">
                        <div className="bg-white shadow sm:rounded-lg">
                            <div className="px-4 py-5 sm:p-6">
                                <h1 className="text-3xl font-bold text-gray-900 mb-6">
                                    Create New Event
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

                                    <div>
                                        <label htmlFor="description_md" className="block text-sm font-medium text-gray-700">
                                            Description
                                        </label>
                                        <textarea
                                            id="description_md"
                                            rows={4}
                                            value={data.description_md}
                                            onChange={(e) => setData('description_md', e.target.value)}
                                            className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                                        />
                                        {errors.description_md && (
                                            <p className="mt-1 text-sm text-red-600">
                                                {errors.description_md}
                                            </p>
                                        )}
                                    </div>

                                    <div className="grid grid-cols-2 gap-4">
                                        <div>
                                            <label htmlFor="start_at" className="block text-sm font-medium text-gray-700">
                                                Start Date & Time
                                            </label>
                                            <input
                                                type="datetime-local"
                                                id="start_at"
                                                value={data.start_at}
                                                onChange={(e) => setData('start_at', e.target.value)}
                                                className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                                            />
                                            {errors.start_at && (
                                                <p className="mt-1 text-sm text-red-600">
                                                    {errors.start_at}
                                                </p>
                                            )}
                                        </div>

                                        <div>
                                            <label htmlFor="end_at" className="block text-sm font-medium text-gray-700">
                                                End Date & Time
                                            </label>
                                            <input
                                                type="datetime-local"
                                                id="end_at"
                                                value={data.end_at}
                                                onChange={(e) => setData('end_at', e.target.value)}
                                                className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                                            />
                                            {errors.end_at && (
                                                <p className="mt-1 text-sm text-red-600">
                                                    {errors.end_at}
                                                </p>
                                            )}
                                        </div>
                                    </div>

                                    <div className="flex justify-end">
                                        <button
                                            type="submit"
                                            disabled={processing}
                                            className="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed"
                                        >
                                            {processing ? 'Creating...' : 'Create Event'}
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
