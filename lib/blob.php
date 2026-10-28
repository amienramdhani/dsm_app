<?php
/** Pengganti Blob Apps Script (file upload / file hasil). */
final class Blob {
    public function __construct(private string $data, private string $type = 'application/octet-stream', private string $name = '') {}
    public static function fromUpload(array $f): self {
        $data = ($f['tmp_name'] ?? '') !== '' && is_uploaded_file($f['tmp_name']) ? (string) file_get_contents($f['tmp_name']) : '';
        return new self($data, (string) ($f['type'] ?? 'application/octet-stream'), (string) ($f['name'] ?? ''));
    }
    public function getName(): string { return $this->name; }
    public function setName(string $n): self { $this->name = $n; return $this; }
    public function getContentType(): string { return $this->type; }
    public function setContentType(string $t): self { $this->type = $t; return $this; }
    /** Isi mentah (byte). Di JS: getBytes().length → strlen($b->getBytes()). */
    public function getBytes(): string { return $this->data; }
    public function size(): int { return strlen($this->data); }
    public function getDataAsString(string $charset = 'UTF-8'): string { return $this->data; }
    public function copyBlob(): self { return new self($this->data, $this->type, $this->name); }
    public function jsonSerialize(): array { return ['name' => $this->name, 'size' => strlen($this->data)]; }
}
function is_blob($v): bool { return $v instanceof Blob; }
/** file?.getName?.() — nama file upload, '' kalau tidak ada file. */
function blob_name($v): string { return $v instanceof Blob ? $v->getName() : ''; }
