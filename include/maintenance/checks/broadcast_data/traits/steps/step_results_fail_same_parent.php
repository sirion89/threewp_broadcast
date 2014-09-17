<?php

namespace threewp_broadcast\maintenance\checks\broadcast_data\traits\steps;

trait step_results_fail_same_parent
{
	public function step_results_fail_same_parent( $o )
	{
		// Clean up those references that have only one child.
		foreach( $this->data->same_parent as $blog_id => $blog )
		{
			foreach( $blog as $post_id => $children )
			{
				if ( $children->count() < 2 )
					$blog->forget( $post_id );
			}
			if ( $blog->count() < 1 )
				$this->data->same_parent->forget( $blog_id );
		}
		if ( count( $this->data->same_parent ) < 1 )
			return;

		$button = $o->form->primary_button( 'same_parent' )
			->value( 'Delete duplicate, orphan posts' );

		if ( $button->pressed() )
		{
			foreach( $this->data->same_parent as $blog_id => $blog )
			{
				foreach( $blog as $post_id => $posts )
				{
					foreach( $posts as $id => $post )
					{
						if ( isset( $post->link ) )
							continue;

						// Delete the BC data first otherwise it will start related posts.
						$o->bc->sql_delete_broadcast_data( $id );

						// Now it is safe to delete the post itself.
						switch_to_blog( $post->blog_id );
						wp_delete_post( $post->post_id, true );
						restore_current_blog();

						$posts->forget( $id );
					}
					$blog->forget( $post_id );
				}
				$this->data->same_parent->forget( $blog_id );
			}
			$o->bc->message( 'The orphaned children have been deleted.' );
			return;
		}

		$o->r .= $o->bc->h3( 'Same parents' );

		$o->r .= $o->bc->p( 'Several child posts point to the same parent, but only one child is linked. The linked children are displayed in bold. The unlinked, orphaned children will be deleted and their broadcast data purged.' );
		$table = $o->bc->table();
		$row = $table->head()->row();
		$row->th()->text( 'Parent post' );
		$row->th()->text( 'Children' );

		foreach( $this->data->same_parent as $blog_id => $blog )
		{
			foreach( $blog as $post_id => $posts )
			{
				$row = $table->body()->row();
				$row->td()->textf( 'Blog %s, post %s', $blog_id, $post_id );
				$text = [];
				foreach( $posts as $id => $post )
				{
					$t = sprintf( 'Blog %s, post %s, ID %s', $post->blog_id, $post->post_id, $id );

					// Is this child the linked child?
					foreach( $post->parent_bcd->get_linked_children() as $child_blog_id => $child_post_id )
						if ( $child_blog_id == $post->blog_id )
							if ( $post->post_id == $child_post_id )
							{
								$post->link = true;
								$posts->set( $id, $post );
								$t = sprintf( '<strong>%s</strong>', $t );
							}
					$text []= $t;
				}
				$row->td()->text( implode( '<br/>', $text ) );
			}
		}

		$o->r .= $table;
		$o->r .= $o->bc->p( $button->display_input() );
	}
}