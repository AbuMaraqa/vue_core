import { toRaw } from 'vue';

const keys = new WeakMap<object, number>();
let lastKey = 0;

/**
 * A stable `:key` for a row of an editable list, so removing a row does not
 * hand its inputs' state to the next one, as an index key would. The key is
 * kept beside the row instead of on it, so submitted data is unchanged; the
 * raw object is used so a row and its reactive proxy share one key.
 */
export function rowKey(row: object): number {
    const raw = toRaw(row);
    let key = keys.get(raw);

    if (key === undefined) {
        key = ++lastKey;
        keys.set(raw, key);
    }

    return key;
}
