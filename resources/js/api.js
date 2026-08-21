export async function apiRequest(url, options = {}) {
    if (! navigator.onLine) throw new Error('You are offline. Reconnect before saving changes.');

    let response;
    try {
        response = await fetch(url, {
            ...options,
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                ...options.headers,
            },
        });
    } catch (error) {
        if (error.name === 'AbortError') throw error;
        throw new Error('Elara could not be reached. Check your connection and try again.');
    }

    const payload = response.headers.get('content-type')?.includes('application/json')
        ? await response.json()
        : {};

    if (! response.ok) {
        const error = new Error(payload.message || (response.status === 419 ? 'Your session expired. Refresh the page and try again.' : 'The request could not be completed.'));
        error.response = response;
        error.payload = payload;
        throw error;
    }

    return payload;
}
