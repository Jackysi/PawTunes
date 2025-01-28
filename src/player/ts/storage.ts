/**
 * PawTunes Project - Open Source Radio Player
 *
 * @author       Jacky (Jaka Prasnikar)
 * @email        jacky@prahec.com
 * @website      https://prahec.com
 * @repository   https://github.com/Jackysi/pawtunes
 * This file is part of the PawTunes open-source project.
 * Contributions and feedback are welcome! Visit the repository or website for more details.
 */

export default class Storage {

    /**
     * Prefix for localStorage keys
     */
    prefix: string = "";


    constructor(prefix: string = "") {

        this.prefix = prefix;

    }


    /**
     * Return if supported
     *
     * @returns {Storage}
     */
    isLocalStorageSupported(): boolean {
        return typeof window.localStorage !== 'undefined';
    }


    /**
     * Set localStorage value
     * If expires is set, it will be set as timestamp
     * Note: This has a bit of a blind spot, if you previously set expiration and then set new value without expiration, it will still be expired
     *
     * @param key
     * @param value
     * @param expires
     * @returns {boolean}
     */
    set(key: any, value: any, expires = 0): boolean {

        if (!this.isLocalStorageSupported() || !key) {
            return false;
        }

        // Add prefix to key (if set)
        key = this.prefix + key;

        // Set value
        localStorage.setItem(key, value);

        // Set expiration
        if (expires > 0) {

            let now = new Date();
            now.setSeconds(now.getSeconds() + expires);
            localStorage.setItem(key + '_expires', now.getTime().toString());

        }

        return true;

    }


    /**
     * Get localStorage value
     *  If the key is expired, it will return false
     *
     * @param key
     * @returns {string|boolean}
     */
    get(key: any): any {

        if (!this.isLocalStorageSupported() || !key) {
            return false;
        }

        // Add prefix to key (if set)
        key = this.prefix + key;

        // Check if key exists
        if (localStorage.getItem(key) == null) {
            return false;
        }

        // Check if the key is expired
        if (localStorage.getItem(key + '_expires') !== null) {

            let now = new Date().getTime();
            if (now > parseInt(localStorage.getItem(key + '_expires') ?? '')) {

                // Delete expired key
                this.delete(key);
                return false;

            }

        }

        // Return value
        return localStorage.getItem(key) ?? false;

    }


    /**
     * Delete localStorage value
     *
     * @param key
     * @returns {boolean}
     */
    delete(key: any): boolean {

        if (!this.isLocalStorageSupported() || !key) {
            return false;
        }

        // Add prefix to key (if set)
        key = this.prefix + key;

        localStorage.removeItem(key);
        localStorage.removeItem(key + '_expires');

        return true;

    }

};