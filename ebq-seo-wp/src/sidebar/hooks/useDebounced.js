import { useEffect, useState } from '@wordpress/element';

export default function useDebounced(value, delayMs = 350) {
	const [v, setV] = useState(value);
	useEffect(() => {
		const t = setTimeout(() => setV(value), delayMs);
		return () => clearTimeout(t);
	}, [value, delayMs]);
	return v;
}
