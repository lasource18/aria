import React from 'react';
import { Head } from '@inertiajs/react';

interface Props {
    message: string;
}

export default function Test({ message }: Props) {
    return (
        <>
            <Head title="Test" />
            <div className="min-h-screen flex items-center justify-center bg-gray-100">
                <div className="bg-white p-8 rounded-lg shadow-lg">
                    <h1 className="text-4xl font-bold text-gray-900">{message}</h1>
                    <p className="mt-4 text-gray-600">Inertia.js is working correctly!</p>
                </div>
            </div>
        </>
    );
}
