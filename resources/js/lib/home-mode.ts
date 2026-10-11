/** Only called when entering the root: explicit /host and /join choices never resize-redirect. */
export function initialHomePath(
    width: number,
    height: number,
): '/join' | '/host' {
    const portraitTablet = width < 1024 && height > width;
    const landscapePhone = width < 1024 && height < 500;
    return width < 768 || portraitTablet || landscapePhone ? '/join' : '/host';
}
