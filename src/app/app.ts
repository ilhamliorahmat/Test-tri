import { ChangeDetectionStrategy, Component, OnInit, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';

interface UserSession {
  id: number;
  username: string;
  role: 'admin' | 'curator';
  full_name: string;
}

interface Gallery {
  id: number;
  title: string;
  description: string;
  code: string;
  is_private: number;
  curator_name?: string;
  photo_count?: number;
  watermark_text?: string;
  opacity?: number;
  font_size?: number;
  position?: string;
  color_hex?: string;
  photos?: Photo[];
}

interface Photo {
  id: number;
  gallery_id: number;
  filename: string;
  original_name: string;
  title: string;
  caption: string;
  file_size: number;
  width: number;
  height: number;
  uploaded_at: string;
}

interface AuditLog {
  id: number;
  user_id: number;
  username: string;
  action: string;
  target_type: string;
  target_id: string;
  details: string;
  ip_address: string;
  created_at: string;
}

interface StaffUser {
  id: number;
  username: string;
  role: 'admin' | 'curator';
  full_name: string;
  created_at: string;
}

@Component({
  changeDetection: ChangeDetectionStrategy.OnPush,
  selector: 'app-root',
  standalone: true,
  imports: [CommonModule, FormsModule],
  templateUrl: './app.html',
  styleUrl: './app.css',
})
export class App implements OnInit {
  activeTab = signal<'exhibitions' | 'curator' | 'admin'>('exhibitions');
  currentUser = signal<UserSession | null>(null);

  galleries = signal<Gallery[]>([]);
  activeGallery = signal<Gallery | null>(null);
  activeCode = signal<string>('');

  showUnlockModal = signal<boolean>(false);
  unlockCodeInput = signal<string>('');
  unlockError = signal<string>('');

  showLoginModal = signal<boolean>(false);
  loginUsername = signal<string>('admin');
  loginPassword = signal<string>('admin123');
  loginError = signal<string>('');

  showCreateGalleryModal = signal<boolean>(false);
  newGalleryTitle = signal<string>('');
  newGalleryDesc = signal<string>('');
  newGalleryCode = signal<string>('');
  newGalleryPrivate = signal<boolean>(true);

  selectedWatermarkGallery = signal<Gallery | null>(null);
  wmText = signal<string>('');
  wmOpacity = signal<number>(50);
  wmFontSize = signal<number>(24);
  wmPosition = signal<string>('bottom-right');
  wmColorHex = signal<string>('#FFFFFF');

  uploadGalleryId = signal<number | null>(null);
  uploadTitle = signal<string>('');
  uploadCaption = signal<string>('');
  selectedFile: File | null = null;
  isUploading = signal<boolean>(false);

  staffUsers = signal<StaffUser[]>([]);
  auditLogs = signal<AuditLog[]>([]);
  showAddUserModal = signal<boolean>(false);
  newStaffUsername = signal<string>('');
  newStaffPassword = signal<string>('');
  newStaffName = signal<string>('');
  newStaffRole = signal<'admin' | 'curator'>('curator');

  lightboxPhoto = signal<{ url: string; title: string; caption: string } | null>(null);

  async ngOnInit() {
    await this.fetchUserStatus();
    await this.loadGalleries();
  }

  async fetchUserStatus() {
    try {
      const res = await fetch('/api/auth/status');
      const data = await res.json();
      if (data.authenticated && data.user) {
        this.currentUser.set(data.user);
      } else {
        this.currentUser.set(null);
      }
    } catch {
      // Ignored
    }
  }

  async loadGalleries() {
    try {
      const res = await fetch('/api/galleries');
      const data = await res.json();
      this.galleries.set(data.galleries || []);
    } catch {
      // Ignored
    }
  }

  async openGallery(gallery: Gallery) {
    const code = gallery.is_private ? gallery.code : '';
    try {
      const res = await fetch(`/api/galleries?id=${gallery.id}&code=${code}`);
      if (res.status === 403) {
        this.unlockCodeInput.set(gallery.code);
        this.showUnlockModal.set(true);
        return;
      }
      const data = await res.json();
      if (data.gallery) {
        this.activeGallery.set(data.gallery);
        this.activeCode.set(code || data.gallery.code);
        this.wmText.set(data.gallery.watermark_text || `© ${data.gallery.title.toUpperCase()}`);
        this.wmOpacity.set(data.gallery.opacity || 50);
        this.wmFontSize.set(data.gallery.font_size || 24);
        this.wmPosition.set(data.gallery.position || 'bottom-right');
      }
    } catch {
      // Ignored
    }
  }

  closeActiveGallery() {
    this.activeGallery.set(null);
  }

  async submitUnlockCode() {
    const code = this.unlockCodeInput().trim();
    if (!code) return;

    try {
      const res = await fetch('/api/galleries/verify-code', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ code })
      });
      const data = await res.json();
      if (data.success) {
        this.showUnlockModal.set(false);
        this.unlockError.set('');
        await this.openGalleryById(data.gallery_id, code);
      } else {
        this.unlockError.set('Invalid gallery access code.');
      }
    } catch {
      this.unlockError.set('Verification failed. Try again.');
    }
  }

  async openGalleryById(id: number, code: string) {
    const res = await fetch(`/api/galleries?id=${id}&code=${code}`);
    const data = await res.json();
    if (data.gallery) {
      this.activeGallery.set(data.gallery);
      this.activeCode.set(code);
    }
  }

  async submitLogin() {
    try {
      const res = await fetch('/api/auth/login', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          username: this.loginUsername(),
          password: this.loginPassword()
        })
      });
      const data = await res.json();
      if (data.success) {
        this.currentUser.set(data.user);
        this.showLoginModal.set(false);
        this.loginError.set('');
        await this.loadGalleries();
      } else {
        this.loginError.set(data.error || 'Authentication failed');
      }
    } catch {
      this.loginError.set('Login error occurred');
    }
  }

  async logout() {
    await fetch('/api/auth/logout', { method: 'POST' });
    this.currentUser.set(null);
    this.activeTab.set('exhibitions');
    await this.loadGalleries();
  }

  async submitCreateGallery() {
    const title = this.newGalleryTitle().trim();
    const code = this.newGalleryCode().trim();
    if (!title || !code) return;

    try {
      const res = await fetch('/api/galleries', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          title,
          description: this.newGalleryDesc(),
          code,
          is_private: this.newGalleryPrivate(),
          user_id: this.currentUser()?.id,
          username: this.currentUser()?.username
        })
      });
      const data = await res.json();
      if (data.success) {
        this.showCreateGalleryModal.set(false);
        this.newGalleryTitle.set('');
        this.newGalleryDesc.set('');
        this.newGalleryCode.set('');
        await this.loadGalleries();
      } else {
        alert(data.error || 'Failed to create gallery');
      }
    } catch {
      alert('Error creating gallery');
    }
  }

  async deleteGallery(id: number) {
    if (!confirm('Are you sure you want to delete this exhibition and all associated photos?')) return;
    await fetch(`/api/galleries/${id}`, { method: 'DELETE' });
    await this.loadGalleries();
    if (this.activeGallery()?.id === id) {
      this.activeGallery.set(null);
    }
  }

  onFileSelected(event: Event) {
    const input = event.target as HTMLInputElement;
    if (input.files && input.files.length > 0) {
      this.selectedFile = input.files[0];
    }
  }

  async submitUploadPhoto(galleryId: number) {
    if (!this.selectedFile) {
      alert('Please select a photo file');
      return;
    }

    this.isUploading.set(true);
    const formData = new FormData();
    formData.append('photo', this.selectedFile);
    formData.append('gallery_id', String(galleryId));
    formData.append('title', this.uploadTitle());
    formData.append('caption', this.uploadCaption());

    try {
      const res = await fetch('/api/photos/upload', {
        method: 'POST',
        body: formData
      });
      const data = await res.json();
      if (data.success) {
        this.uploadTitle.set('');
        this.uploadCaption.set('');
        this.selectedFile = null;
        if (this.activeGallery()?.id === galleryId) {
          await this.openGallery(this.activeGallery()!);
        }
        await this.loadGalleries();
      } else {
        alert(data.error || 'Upload failed');
      }
    } catch {
      alert('Photo upload failed');
    } finally {
      this.isUploading.set(false);
    }
  }

  async deletePhoto(photoId: number) {
    if (!confirm('Delete photo from exhibition?')) return;
    await fetch(`/api/photos/${photoId}`, { method: 'DELETE' });
    if (this.activeGallery()) {
      await this.openGallery(this.activeGallery()!);
    }
  }

  async saveWatermarkSettings(galleryId: number) {
    try {
      const res = await fetch('/api/watermark', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          gallery_id: galleryId,
          text: this.wmText(),
          opacity: this.wmOpacity(),
          font_size: this.wmFontSize(),
          position: this.wmPosition(),
          color_hex: this.wmColorHex()
        })
      });
      const data = await res.json();
      if (data.success) {
        alert('Watermark settings saved successfully!');
        if (this.activeGallery()?.id === galleryId) {
          await this.openGallery(this.activeGallery()!);
        }
      }
    } catch {
      alert('Failed to save watermark configuration');
    }
  }

  async setTab(tab: 'exhibitions' | 'curator' | 'admin') {
    this.activeTab.set(tab);
    if (tab === 'exhibitions') await this.loadGalleries();
    if (tab === 'admin') {
      await this.loadStaffUsers();
      await this.loadAuditLogs();
    }
  }

  async loadStaffUsers() {
    try {
      const res = await fetch('/api/users');
      const data = await res.json();
      this.staffUsers.set(data.users || []);
    } catch {
      // Ignored
    }
  }

  async loadAuditLogs() {
    try {
      const res = await fetch('/api/audit');
      const data = await res.json();
      this.auditLogs.set(data.audit_logs || []);
    } catch {
      // Ignored
    }
  }

  async submitAddStaffUser() {
    const username = this.newStaffUsername().trim();
    const password = this.newStaffPassword().trim();
    const full_name = this.newStaffName().trim();

    if (!username || !password || !full_name) return;

    try {
      const res = await fetch('/api/users', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          username,
          password,
          full_name,
          role: this.newStaffRole()
        })
      });
      const data = await res.json();
      if (data.success) {
        this.showAddUserModal.set(false);
        this.newStaffUsername.set('');
        this.newStaffPassword.set('');
        this.newStaffName.set('');
        await this.loadStaffUsers();
        await this.loadAuditLogs();
      } else {
        alert(data.error || 'Failed to create user');
      }
    } catch {
      alert('Error creating staff account');
    }
  }

  async deleteStaffUser(id: number) {
    if (!confirm('Are you sure you want to delete this staff user account?')) return;
    await fetch(`/api/users/${id}`, { method: 'DELETE' });
    await this.loadStaffUsers();
    await this.loadAuditLogs();
  }

  openLightbox(photo: Photo) {
    const code = this.activeCode() || this.activeGallery()?.code || '';
    const url = `/api/images/${photo.id}?code=${code}`;
    this.lightboxPhoto.set({
      url,
      title: photo.title,
      caption: photo.caption
    });
  }

  closeLightbox() {
    this.lightboxPhoto.set(null);
  }
}
